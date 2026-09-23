<?php

namespace App\Http\Controllers\Store;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Branch\Branch;
use App\Models\Master\Vendor;
use App\Models\Store\StoreDoc;
use App\Models\Store\StoreDocItem;
use App\Models\Store\StoreItem;
use App\Support\Notify;
use App\Support\PostingRefused;
use App\Support\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Every piece of store paperwork, through one controller.
 *
 * A purchase order, a goods receipt, an issue to the kitchen, a wastage note
 * and a stock correction are the same screen with different words on it, so
 * they are the same code with a `kind` in the URL. What differs between them —
 * which fields matter, which way the stock moves, what the buttons say — is
 * stated once, in `shape()`, rather than five times in five controllers.
 *
 * Nothing here moves stock. Saving writes a draft; posting is a separate,
 * deliberate act, and posting is the only thing that touches the ledger.
 */
class DocController extends Controller
{
    /** The five kinds, and what is true about each. */
    private const SHAPE = [
        'po' => [
            'label' => 'Purchase Order', 'plural' => 'Purchase Orders',
            'permission' => 'store/purchase-orders',
            'party' => 'vendor', 'icon' => 'file',
            'blurb' => 'What has been ordered, and from whom. Ordering moves no stock — a receipt does.',
            'post' => 'Place the order',
        ],
        'grn' => [
            'label' => 'Goods Receipt', 'plural' => 'Goods Receipts',
            'permission' => 'store/grn',
            'party' => 'vendor', 'icon' => 'package',
            'blurb' => 'What actually turned up. Posting this puts it on the shelf and re-averages its cost.',
            'post' => 'Receive into stock',
        ],
        'issue' => [
            'label' => 'Issue', 'plural' => 'Issues',
            'permission' => 'store/issues',
            'party' => 'department', 'icon' => 'arrow-right',
            'blurb' => 'What left the store, and who took it. Issued at the average rate, not the last price paid.',
            'post' => 'Issue the stock',
        ],
        'wastage' => [
            'label' => 'Wastage', 'plural' => 'Wastage',
            'permission' => 'store/wastage',
            'party' => 'department', 'icon' => 'trash',
            'blurb' => 'What was thrown away. Costed like an issue, so it lands in the food cost where it belongs.',
            'post' => 'Write it off',
        ],
        'adjustment' => [
            'label' => 'Stock Adjustment', 'plural' => 'Stock Adjustments',
            'permission' => 'store/adjustments',
            'party' => 'none', 'icon' => 'refresh',
            'blurb' => 'A physical count correcting the books. A positive quantity adds, a negative one takes away.',
            'post' => 'Apply the correction',
        ],
        'transfer_out' => [
            'label' => 'Transfer Out', 'plural' => 'Stock Transfers',
            'permission' => 'store/transfer',
            'party' => 'outlet', 'icon' => 'arrow-right',
            'blurb' => 'What is being sent to another outlet. Posting takes it off this outlet\'s shelf.',
            'post' => 'Send it',
        ],
        'transfer_in' => [
            'label' => 'Transfer In', 'plural' => 'Transfers Received',
            'permission' => 'store/transfer',
            'party' => 'outlet', 'icon' => 'package',
            'blurb' => 'What actually turned up from another outlet. Posting puts it on this outlet\'s shelf.',
            'post' => 'Receive into stock',
        ],
    ];

    /**
     * The sidebar (and the permission matrix) know three of these documents
     * by the same long, readable slug as their permission key —
     * "purchase-orders", not "po". The database enum and this controller's
     * own internals have always used the short code. Rather than rename
     * either side — which would mean an enum migration on one hand, or
     * resetting every role's permission grants on the other — a document
     * reached by its long slug is normalised to the short one right here,
     * before anything else runs. Every link this controller itself renders
     * already uses the short form, so there is nothing to translate on the
     * way back out.
     */
    private const ALIASES = [
        'purchase-orders' => 'po',
        'issues' => 'issue',
        'adjustments' => 'adjustment',
        // The nav link and the permission both say "transfer" — a bare visit
        // to it means "send", the same way a bare visit to Purchase Orders
        // means raising one rather than receiving against one.
        'transfer' => 'transfer_out',
    ];

    /** GET store/{kind} */
    public function index(Request $request, string $kind): View
    {
        $kind = self::ALIASES[$kind] ?? $kind;
        $shape = $this->shape($kind, 'view');
        $branchId = (int) Helper::getActiveBranchId();

        $docs = StoreDoc::query()
            ->forBranch($branchId)
            ->ofKind($kind)
            ->with(['vendor', 'items', 'toBranch', 'against.branch'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($q, $t) => $q->where(function ($q) use ($t) {
                $q->where('doc_no', 'like', "%{$t}%")->orWhere('invoice_no', 'like', "%{$t}%");
            }))
            ->orderByDesc('doc_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('store.docs', [
            'kind' => $kind,
            'shape' => $shape,
            'docs' => $docs,
            'filters' => [
                'status' => $request->string('status')->toString(),
                'q' => $request->string('q')->toString(),
            ],
        ]);
    }

    /** GET store/{kind}/new */
    public function create(Request $request, string $kind): View|RedirectResponse
    {
        $kind = self::ALIASES[$kind] ?? $kind;
        $shape = $this->shape($kind, 'add');
        $branchId = (int) Helper::getActiveBranchId();

        /*
         * Nothing to receive without saying which transfer — sent here with
         * no ?from=, the useful thing is the list of what is waiting, not an
         * empty items form nobody asked for.
         */
        if ($kind === 'transfer_in' && ! $request->integer('from')) {
            return redirect()->route('store.transfer.inbox');
        }

        /*
         * A goods receipt raised from a purchase order arrives pre-filled with
         * what is still outstanding on it, so the storekeeper types only what
         * differs. Short deliveries are the normal case, not the exception.
         */
        $po = $kind === 'grn' && $request->integer('from')
            ? StoreDoc::forBranch($branchId)->ofKind('po')->with('items.item')->find($request->integer('from'))
            : null;

        /*
         * The transfer_out equivalent of $po above — except it belongs to
         * ANOTHER branch (the one that sent it), so it is looked up without
         * forBranch(), and ownership is checked the other way round: this
         * outlet must be the one it was addressed to.
         */
        $transferOut = null;

        if ($kind === 'transfer_in' && $request->integer('from')) {
            $transferOut = StoreDoc::ofKind('transfer_out')->with('items.item', 'branch')->find($request->integer('from'));
            abort_unless($transferOut && (int) $transferOut->to_branch_id === $branchId, 404);
        }

        $against = $po ?: $transferOut;

        return view('store.doc-form', [
            'kind' => $kind,
            'shape' => $shape,
            'doc' => null,
            'po' => $po,
            'transferOut' => $transferOut,
            'prefill' => $against
                ? $against->items
                    ->filter(fn (StoreDocItem $line) => $line->pending > 0)
                    ->map(fn (StoreDocItem $line) => [
                        'store_item_id' => $line->store_item_id,
                        'qty' => $line->pending,
                        'rate' => (float) $line->rate,
                        'tax_percent' => (float) $line->tax_percent,
                    ])->values()->all()
                : [],
            'items' => StoreItem::forBranch($branchId)->active()->orderBy('name')->get(),
            'vendors' => Vendor::forBranch($branchId)->active()->orderBy('name')->pluck('name', 'id'),
            'departments' => Store::DEPARTMENTS,
            'branches' => $shape['party'] === 'outlet'
                ? Branch::where('status', 1)->where('id', '!=', $branchId)->orderBy('branch_name')->pluck('branch_name', 'id')
                : collect(),
            'openOrders' => $kind === 'grn'
                ? StoreDoc::forBranch($branchId)->ofKind('po')->whereIn('status', ['posted', 'partial'])
                    ->orderByDesc('doc_date')->limit(50)->get()
                : collect(),
            'nextNo' => Store::nextNo($branchId, $kind),
        ]);
    }

    /** GET store/{kind}/{doc}/edit */
    public function edit(string $kind, StoreDoc $doc): View
    {
        $kind = self::ALIASES[$kind] ?? $kind;
        $shape = $this->shape($kind, 'edit');
        $branchId = (int) Helper::getActiveBranchId();

        abort_unless((int) $doc->branch_id === $branchId && $doc->kind === $kind, 404);

        if (! $doc->isDraft()) {
            abort(403, 'A posted document cannot be edited. Cancel it and raise another.');
        }

        return view('store.doc-form', [
            'kind' => $kind,
            'shape' => $shape,
            'doc' => $doc->load('items'),
            'po' => $kind === 'grn' ? $doc->against : null,
            // Editing a draft transfer_in needs the same "receiving against"
            // alert and branches list create() gives it — see the note there.
            'transferOut' => $kind === 'transfer_in' ? $doc->against?->load('branch') : null,
            'prefill' => $doc->items->map(fn ($line) => [
                'store_item_id' => $line->store_item_id,
                'qty' => (float) $line->qty,
                'rate' => (float) $line->rate,
                'tax_percent' => (float) $line->tax_percent,
            ])->all(),
            'items' => StoreItem::forBranch($branchId)->active()->orderBy('name')->get(),
            'vendors' => Vendor::forBranch($branchId)->active()->orderBy('name')->pluck('name', 'id'),
            'departments' => Store::DEPARTMENTS,
            'branches' => $shape['party'] === 'outlet'
                ? Branch::where('status', 1)->where('id', '!=', $branchId)->orderBy('branch_name')->pluck('branch_name', 'id')
                : collect(),
            'openOrders' => collect(),
            'nextNo' => $doc->doc_no,
        ]);
    }

    /** POST store/{kind}  ·  PUT store/{kind}/{doc} */
    public function save(Request $request, string $kind, ?StoreDoc $doc = null): RedirectResponse
    {
        $kind = self::ALIASES[$kind] ?? $kind;
        $isNew = ! ($doc && $doc->exists);
        $this->shape($kind, $doc && $doc->exists ? 'edit' : 'add');
        $branchId = (int) Helper::getActiveBranchId();

        if ($doc && $doc->exists) {
            abort_unless((int) $doc->branch_id === $branchId && $doc->kind === $kind, 404);
            abort_unless($doc->isDraft(), 403, 'A posted document cannot be edited.');
        } else {
            $doc = null;
        }

        // An adjustment may take stock away, so its quantity is the one that
        // is allowed to be negative.
        $qtyRule = $kind === 'adjustment'
            ? ['required', 'numeric', 'min:-999999', 'max:999999', 'not_in:0']
            : ['required', 'numeric', 'min:0.001', 'max:999999'];

        /*
         * The form always shows a few blank rows to type into — three for a
         * new document. Most documents don't need all of them, and leaving
         * one empty is not a mistake, so a row nobody put anything into is
         * dropped here rather than forcing a manual "remove this line" click
         * before the document can be saved at all. A row the person did
         * start — an item chosen, or just a quantity typed — still has to
         * be finished; only a row untouched in both fields is silently gone.
         */
        $request->merge([
            'lines' => collect($request->input('lines', []))
                ->filter(fn ($line) => filled($line['store_item_id'] ?? null) || filled($line['qty'] ?? null))
                ->values()
                ->all(),
        ]);

        $data = $request->validate([
            'doc_date' => ['required', 'date'],
            'vendor_id' => ['nullable', 'integer', Rule::exists('vendors', 'id')],
            'department' => ['nullable', Rule::in(array_keys(Store::DEPARTMENTS))],
            'issued_to' => ['nullable', 'string', 'max:255'],
            'invoice_no' => ['nullable', 'string', 'max:60'],
            'invoice_date' => ['nullable', 'date'],
            'expected_on' => ['nullable', 'date'],
            'against_id' => ['nullable', 'integer'],
            // Required to send a transfer, meaningless for the other six kinds
            // — 'nullable' and 'required' don't mix, so the condition picks
            // one or the other rather than stacking both.
            'to_branch_id' => [
                $kind === 'transfer_out' ? 'required' : 'nullable',
                'integer', Rule::exists('branches', 'id'), Rule::notIn([$branchId]),
            ],
            'other_charges' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'remark' => ['nullable', 'string', 'max:1000'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.store_item_id' => ['required', 'integer', Rule::exists('store_items', 'id')],
            'lines.*.qty' => $qtyRule,
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lines.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.remark' => ['nullable', 'string', 'max:255'],
        ]);

        /*
         * The same item twice on one document is almost always a typo, and it
         * makes a purchase order impossible to settle — two lines for the same
         * thing, and no way to say which the delivery filled.
         */
        $ids = array_column($data['lines'], 'store_item_id');

        if (count($ids) !== count(array_unique($ids))) {
            return back()->withInput()->with('error',
                'The same item is on this document more than once. Put the whole quantity on one line.');
        }

        /*
         * A transfer can only carry an item the destination outlet is also
         * set up to use. Nothing else guards this: the receiving screen
         * builds its item list from the destination branch (StoreItem::
         * forBranch()), so a line here it can't see is a line that arrives
         * with no way to receive it — caught here, at the only point that
         * already knows both branches, rather than as a confusing blank
         * dropdown on the other outlet's screen later.
         */
        if ($kind === 'transfer_out') {
            $available = StoreItem::whereIn('id', $ids)
                ->where(function ($q) use ($data) {
                    $q->where('branch_id', $data['to_branch_id'])->orWhereNull('branch_id');
                })
                ->pluck('id');

            $missing = collect($ids)->diff($available);

            if ($missing->isNotEmpty()) {
                $names = StoreItem::whereIn('id', $missing)->pluck('name')->implode(', ');
                $plural = $missing->count() > 1;

                return back()->withInput()->with('error',
                    $names . ' ' . ($plural ? "aren't" : "isn't") . ' set up at the destination outlet, so '
                    . ($plural ? 'they' : 'it') . " can't be sent there.");
            }
        }

        $doc = DB::transaction(function () use ($data, $kind, $branchId, $doc, $request) {
            $totals = Store::totals($data['lines']);
            $other = round((float) ($data['other_charges'] ?? 0), 2);

            $header = [
                'branch_id' => $branchId,
                'kind' => $kind,
                'doc_date' => $data['doc_date'],
                'vendor_id' => $data['vendor_id'] ?? null,
                'department' => $data['department'] ?? null,
                'issued_to' => $data['issued_to'] ?? null,
                'invoice_no' => $data['invoice_no'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'expected_on' => $data['expected_on'] ?? null,
                'against_id' => $data['against_id'] ?? null,
                'to_branch_id' => $data['to_branch_id'] ?? null,
                'sub_total' => $totals['sub_total'],
                'tax_total' => $totals['tax_total'],
                'other_charges' => $other,
                'net_amount' => round($totals['net_amount'] + $other, 2),
                'remark' => $data['remark'] ?? null,
            ];

            if ($doc) {
                $doc->update($header);
                $doc->items()->delete();
            } else {
                $doc = StoreDoc::create($header + [
                    'doc_no' => Store::nextNo($branchId, $kind),
                    'status' => 'draft',
                    'created_by' => $request->user()?->user_id,
                ]);
            }

            foreach ($data['lines'] as $line) {
                $qty = (float) $line['qty'];
                $rate = round((float) ($line['rate'] ?? 0), 2);
                $amount = round(abs($qty) * $rate, 2);
                $taxPercent = (float) ($line['tax_percent'] ?? 0);
                $tax = round($amount * $taxPercent / 100, 2);

                StoreDocItem::create([
                    'store_doc_id' => $doc->id,
                    'store_item_id' => (int) $line['store_item_id'],
                    'qty' => round($qty, 3),
                    'rate' => $rate,
                    'amount' => $amount,
                    'tax_percent' => $taxPercent,
                    'tax_amount' => $tax,
                    'total_amount' => round($amount + $tax, 2),
                    'remark' => $line['remark'] ?? null,
                ]);
            }

            return $doc;
        });

        /*
         * A purchase order is a commitment even as a draft — unlike the
         * other four, which do nothing until posted — so raising one is
         * worth a heads-up on its own, separate from the "it was placed"
         * notification post() sends later.
         */
        if ($isNew && $kind === 'po') {
            Notify::event('store.po.created')
                ->title('Purchase order raised — ' . $doc->doc_no)
                ->body(($doc->vendor?->name ?: 'No supplier chosen yet')
                    . ' · ₹' . number_format((float) $doc->net_amount, 2))
                ->url(route('store.docs.show', ['kind' => 'po', 'doc' => $doc]))
                ->send();
        }

        /*
         * "Save & post" in one click, for the common case of somebody who
         * already knows this document is right and does not want a second
         * screen and a second confirmation just to say so. Choosing that
         * button over "Save as draft" is itself the deliberate act, so
         * nothing further asks to make sure.
         */
        if ($request->input('commit') === 'post') {
            try {
                $result = $this->postAndNotify($kind, $doc, $request->user()?->user_id);
            } catch (PostingRefused $e) {
                return redirect()
                    ->route('store.docs.show', ['kind' => $kind, 'doc' => $doc])
                    ->with('warning', 'Saved as a draft, but could not post it: ' . $e->getMessage());
            }

            return redirect()
                ->route('store.docs.show', ['kind' => $kind, 'doc' => $doc])
                ->with($result['tone'], $result['message']);
        }

        return redirect()
            ->route('store.docs.show', ['kind' => $kind, 'doc' => $doc])
            ->with('status', $doc->kind_label . ' ' . $doc->doc_no . ' saved as a draft. '
                . 'Nothing has moved yet — post it when you are happy with it.');
    }

    /** GET store/{kind}/{doc} */
    public function show(string $kind, StoreDoc $doc): View
    {
        $kind = self::ALIASES[$kind] ?? $kind;
        $shape = $this->shape($kind, 'view');
        $branchId = (int) Helper::getActiveBranchId();

        abort_unless($doc->kind === $kind && $this->canSee($doc, $branchId), 404);

        return view('store.doc', [
            'kind' => $kind,
            'shape' => $shape,
            'doc' => $doc->load(['items.item', 'vendor', 'poster', 'against.branch', 'receipts', 'toBranch']),
            'branch' => Helper::activeBranch(),
            // Viewing a transfer is shared between two branches; editing,
            // posting and cancelling stay the owning branch's alone. The
            // view needs to know which one this is, so it doesn't offer a
            // button the counterpart branch would only get a 404 from.
            'isOwner' => (int) $doc->branch_id === $branchId,
        ]);
    }

    /**
     * Whether this branch is allowed to look at this document.
     *
     * Every other kind is one branch's own paperwork, private to it. A
     * transfer is the one exception — it names two branches on purpose, so
     * both get to see it: the sender while it's on its way, the receiver
     * once it's their turn to act on it. Nothing else (editing, posting,
     * cancelling) gets this relaxation — those stay the owning branch's
     * alone, checked separately wherever they happen.
     */
    private function canSee(StoreDoc $doc, int $branchId): bool
    {
        if ((int) $doc->branch_id === $branchId) {
            return true;
        }

        return match ($doc->kind) {
            'transfer_out' => (int) $doc->to_branch_id === $branchId,
            'transfer_in' => (int) $doc->against?->branch_id === $branchId,
            default => false,
        };
    }

    /** POST store/{kind}/{doc}/post */
    public function post(Request $request, string $kind, StoreDoc $doc): RedirectResponse
    {
        $kind = self::ALIASES[$kind] ?? $kind;

        // Posting moves stock, so it sits behind `add` rather than `edit` — a
        // clerk who may fix a typo on a draft is not automatically somebody
        // who may move the store.
        $this->shape($kind, 'add');
        abort_unless((int) $doc->branch_id === (int) Helper::getActiveBranchId() && $doc->kind === $kind, 404);

        try {
            $result = $this->postAndNotify($kind, $doc, $request->user()?->user_id);
        } catch (PostingRefused $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($result['tone'], $result['message']);
    }

    /**
     * Post a document and tell the world about it — the guts shared by the
     * standalone "Post" action above and the "Save & post" shortcut on the
     * form below, so the two paths cannot quietly drift apart.
     *
     * @return array{tone: string, message: string}
     *
     * @throws PostingRefused when the document is not in a state to be posted
     */
    private function postAndNotify(string $kind, StoreDoc $doc, ?int $userId): array
    {
        $rows = Store::post($doc->id, $userId);

        $doc->refresh();

        // Somebody should be told before the kitchen finds out at dinner.
        $short = $doc->items
            ->map(fn ($line) => $line->item)
            ->filter(fn ($item) => $item && (float) $item->fresh()->current_qty < 0);

        $message = $doc->kind_label . ' ' . $doc->doc_no . ' posted'
            . ($rows ? ' — ' . $rows . ' ' . \Illuminate\Support\Str::plural('item', $rows) . ' moved.' : '.');

        Notify::event('store.' . $kind . '.posted')
            ->title($message)
            ->body($doc->party . ' · ₹' . number_format((float) $doc->net_amount, 2))
            ->url(route('store.docs.show', ['kind' => $kind, 'doc' => $doc]))
            ->send();

        if ($short->isNotEmpty()) {
            return ['tone' => 'warning', 'message' => $message . ' ' . $short->count() . ' '
                . \Illuminate\Support\Str::plural('item', $short->count())
                . ' are now below zero — a delivery note has probably not been entered yet.'];
        }

        return ['tone' => 'status', 'message' => $message];
    }

    /** DELETE store/{kind}/{doc} */
    public function cancel(Request $request, string $kind, StoreDoc $doc): RedirectResponse
    {
        $kind = self::ALIASES[$kind] ?? $kind;
        $this->shape($kind, 'delete');
        abort_unless((int) $doc->branch_id === (int) Helper::getActiveBranchId() && $doc->kind === $kind, 404);

        try {
            $rows = Store::cancel($doc->id, $request->user()?->user_id);
        } catch (PostingRefused $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('warning', $doc->kind_label . ' ' . $doc->doc_no . ' cancelled'
            . ($rows ? ', and ' . $rows . ' movements were reversed. The original rows stay in the ledger.' : '.'));
    }

    /**
     * What this kind of document is, and whether the user may do this to it.
     *
     * The permission lives here rather than in route middleware because it
     * depends on BOTH the kind and the action — receiving stock and writing it
     * off are different levels of trust, and a middleware string cannot say
     * "whichever key this kind maps to". Every action goes through here, so
     * there is no way to add one and forget the check.
     */
    private function shape(string $kind, string $action = 'view'): array
    {
        abort_unless(isset(self::SHAPE[$kind]), 404);

        $shape = self::SHAPE[$kind] + ['kind' => $kind];

        abort_unless(can_do($shape['permission'], $action), 403);

        return $shape;
    }
}
