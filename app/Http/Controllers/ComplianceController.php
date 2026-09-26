<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Country\Country;
use App\Models\FrontOffice\CheckIn;
use App\Models\FrontOffice\FormCEntry;
use App\Support\Compliance;
use App\Support\Gst;
use App\Support\Tally;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ComplianceController extends Controller
{

    public function formC(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $filed = FormCEntry::query()
            ->forBranch($branchId)
            ->with(['checkIn.room', 'nationality'])
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('compliance.form-c', [
            'pending' => Compliance::formCPending($branchId),
            'entries' => $filed,
            'term' => $request->string('q')->toString(),
        ]);
    }

    public function formCCreate(Request $request, CheckIn $checkIn): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        abort_unless((int) $checkIn->branch_id === $branchId, 404);

        $paxId = $request->integer('pax') ?: null;

        $entry = FormCEntry::query()
            ->where('check_in_id', $checkIn->id)
            ->where('check_in_pax_id', $paxId)
            ->first();

        return view('compliance.form-c-edit', [
            'checkIn' => $checkIn->load(['room', 'reservation', 'guest', 'pax']),
            'entry' => $entry,
            'paxId' => $paxId,
            'prefill' => $entry ? [] : Compliance::prefillFormC($checkIn),
            'countries' => Country::orderBy('name')->pluck('name', 'id'),
            'purposes' => Compliance::VISIT_PURPOSES,
        ]);
    }

    public function formCStore(Request $request, CheckIn $checkIn): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        abort_unless((int) $checkIn->branch_id === $branchId, 404);

        $data = $request->validate([
            'check_in_pax_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'nationality_id' => ['nullable', 'integer'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'sex' => ['nullable', Rule::in(['male', 'female', 'other'])],

            'passport_no' => ['required', 'string', 'max:40'],
            'passport_place_of_issue' => ['nullable', 'string', 'max:255'],
            'passport_issue_date' => ['nullable', 'date'],
            'passport_expiry_date' => ['nullable', 'date', 'after:passport_issue_date'],

            'visa_no' => ['nullable', 'string', 'max:40'],
            'visa_type' => ['nullable', 'string', 'max:60'],
            'visa_place_of_issue' => ['nullable', 'string', 'max:255'],
            'visa_issue_date' => ['nullable', 'date'],
            'visa_expiry_date' => ['nullable', 'date', 'after:visa_issue_date'],

            'arrived_in_india_on' => ['nullable', 'date'],
            'arrived_from' => ['nullable', 'string', 'max:255'],
            'purpose_of_visit' => ['nullable', 'string', 'max:120'],

            'permanent_address' => ['nullable', 'string', 'max:1000'],
            'address_in_india' => ['nullable', 'string', 'max:1000'],

            'next_destination' => ['nullable', 'string', 'max:255'],
            'next_destination_on' => ['nullable', 'date'],

            'employer' => ['nullable', 'string', 'max:255'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['employed_in_india'] = $request->boolean('employed_in_india');
        $data['branch_id'] = $branchId;
        $data['check_in_id'] = $checkIn->id;
        $data['check_in_pax_id'] = $data['check_in_pax_id'] ?: null;
        $data['created_by'] = $request->user()?->user_id;

        $entry = FormCEntry::updateOrCreate(
            ['check_in_id' => $checkIn->id, 'check_in_pax_id' => $data['check_in_pax_id']],
            $data
        );

        if (! $checkIn->is_foreign) {
            $checkIn->update([
                'is_foreign' => true,
                'nationality_id' => $checkIn->nationality_id ?: ($data['nationality_id'] ?? null),
            ]);
        }

        return redirect()
            ->route('compliance.form-c')
            ->with('status', 'Form C saved for ' . $entry->name . '. Print it, or file it and record the reference.');
    }

    public function formCFiled(Request $request, FormCEntry $entry): RedirectResponse
    {
        abort_unless((int) $entry->branch_id === (int) Helper::getActiveBranchId(), 404);

        $data = $request->validate([
            'reference_no' => ['nullable', 'string', 'max:60'],
        ]);

        $entry->update([
            'filed_at' => now(),
            'reference_no' => $data['reference_no'] ?? null,
        ]);

        return back()->with('status', 'Marked as filed' . ($entry->reference_no ? ' — ' . $entry->reference_no : '') . '.');
    }

    public function formCPrint(FormCEntry $entry): View
    {
        abort_unless((int) $entry->branch_id === (int) Helper::getActiveBranchId(), 404);

        return view('compliance.form-c-print', [
            'entry' => $entry->load(['checkIn.room', 'nationality']),
            'branch' => Helper::activeBranch(),
            'back' => route('compliance.form-c'),
        ]);
    }

    public function police(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        $date = $this->dateFrom($request, 'date');

        $filters = [
            'foreign' => $request->boolean('foreign'),
            'q' => $request->string('q')->toString() ?: null,
        ];

        $rows = Compliance::register($branchId, $date, $filters);

        return view('compliance.police-register', [
            'rows' => $rows,
            'summary' => Compliance::registerSummary($rows),
            'date' => $date,
            'filters' => $filters,
            'branch' => Helper::activeBranch(),
        ]);
    }

    public function policeExport(Request $request): StreamedResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $date = $this->dateFrom($request, 'date');

        $rows = Compliance::register($branchId, $date, [
            'foreign' => $request->boolean('foreign'),
            'q' => $request->string('q')->toString() ?: null,
        ]);

        abort_unless(can_do('compliance/police-register', 'delete'), 403,
            'Full ID numbers need the Delete permission on this screen.');

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Room', 'Folio', 'Name', 'Relation', 'Age', 'Gender', 'Nationality',
                'ID type', 'ID number', 'Mobile', 'Arrived', 'Departs',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['room_no'], $row['folio_no'], $row['name'], $row['relation'],
                    $row['age'], $row['gender'], $row['nationality'],
                    Compliance::idLabel($row['id_type']), strtoupper((string) $row['id_number']),
                    $row['mobile'], $row['arrived'], $row['departs'],
                ]);
            }

            fclose($out);
        }, 'police-register-' . $date . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function policePrint(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        $date = $this->dateFrom($request, 'date');

        $rows = Compliance::register($branchId, $date, ['foreign' => $request->boolean('foreign')]);

        return view('compliance.police-register-print', [
            'rows' => $rows,
            'summary' => Compliance::registerSummary($rows),
            'date' => $date,
            'branch' => Helper::activeBranch(),
            'back' => route('compliance.police-register', ['date' => $date]),
        ]);
    }

    public function gst(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        $branch = Helper::activeBranch();

        [$from, $to] = $this->monthFrom($request);

        $state = $branch?->gst_state_code ?: Gst::stateOf($branch?->gst_no);

        return view('compliance.gst-returns', [
            'return' => Gst::gstr1($branchId, $from, $to, $state),
            'from' => $from,
            'to' => $to,
            'month' => CarbonImmutable::parse($from)->format('Y-m'),
            'branch' => $branch,
            'state' => $state,
            'stateName' => Gst::stateName($state),
            'states' => Gst::STATES,
        ]);
    }
    public function gstExport(Request $request, string $table): StreamedResponse
    {
        abort_unless(in_array($table, ['b2b', 'b2cl', 'b2cs', 'hsn'], true), 404);

        $branchId = (int) Helper::getActiveBranchId();
        $branch = Helper::activeBranch();
        [$from, $to] = $this->monthFrom($request);

        $state = $branch?->gst_state_code ?: Gst::stateOf($branch?->gst_no);
        $return = Gst::gstr1($branchId, $from, $to, $state);

        $name = 'gstr1-' . $table . '-' . CarbonImmutable::parse($from)->format('Y-m') . '.csv';

        return response()->streamDownload(function () use ($return, $table) {
            $out = fopen('php://output', 'w');

            match ($table) {
                'b2b' => $this->writeInvoices($out, $return['b2b'], true),
                'b2cl' => $this->writeInvoices($out, $return['b2cl'], false),
                'b2cs' => $this->writeB2cs($out, $return['b2cs']),
                'hsn' => $this->writeHsn($out, $return['hsn']),
            };

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    private function writeInvoices($out, $rows, bool $withGstin): void
    {
        fputcsv($out, array_filter([
            $withGstin ? 'GSTIN of recipient' : null,
            $withGstin ? 'Receiver name' : null,
            'Invoice number', 'Invoice date', 'Invoice value',
            'Place of supply', 'Reverse charge', 'Invoice type',
            'Rate', 'Taxable value', 'Tax amount',
        ]));

        foreach ($rows as $bill) {
            foreach ($bill->rates as $rate) {
                fputcsv($out, array_filter([
                    $withGstin ? strtoupper((string) $bill->buyer_gstin) : null,
                    $withGstin ? ($bill->buyer_name ?: $bill->guest_name) : null,
                    $bill->bill_no,
                    CarbonImmutable::parse($bill->bill_date)->format('d-m-Y'),
                    number_format((float) $bill->net_amount, 2, '.', ''),
                    Gst::stateLabel($bill->place_of_supply) ?: $bill->place_of_supply,
                    'N',
                    'Regular B2B',
                    number_format($rate['rate'], 2, '.', ''),
                    number_format($rate['taxable'], 2, '.', ''),
                    number_format($rate['tax'], 2, '.', ''),
                ], fn ($v) => $v !== null));
            }
        }
    }

    private function writeB2cs($out, $rows): void
    {
        fputcsv($out, ['Type', 'Place of supply', 'Rate', 'Taxable value', 'Tax amount']);

        foreach ($rows as $row) {
            fputcsv($out, [
                'OE',
                Gst::stateLabel($row['place']) ?: $row['place'],
                number_format($row['rate'], 2, '.', ''),
                number_format($row['taxable'], 2, '.', ''),
                number_format($row['tax'], 2, '.', ''),
            ]);
        }
    }

    private function writeHsn($out, $rows): void
    {
        fputcsv($out, ['HSN/SAC', 'Description', 'UQC', 'Total quantity', 'Rate', 'Taxable value', 'Tax amount', 'Total value']);

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['code'], $row['description'], $row['uqc'],
                number_format($row['qty'], 2, '.', ''),
                number_format($row['rate'], 2, '.', ''),
                number_format($row['taxable'], 2, '.', ''),
                number_format($row['tax'], 2, '.', ''),
                number_format($row['total'], 2, '.', ''),
            ]);
        }
    }

    public function tally(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        [$from, $to] = $this->monthFrom($request);

        $export = Tally::sales($branchId, $from, $to);

        return view('compliance.tally', [
            'from' => $from,
            'to' => $to,
            'month' => CarbonImmutable::parse($from)->format('Y-m'),
            'count' => $export['count'],
            'total' => $export['total'],
            'ledgers' => Tally::LEDGERS,
            'branch' => Helper::activeBranch(),
        ]);
    }

    public function tallyDownload(Request $request): StreamedResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        [$from, $to] = $this->monthFrom($request);

        $export = Tally::sales($branchId, $from, $to, $request->string('company')->toString() ?: null);
        $name = 'tally-sales-' . CarbonImmutable::parse($from)->format('Y-m') . '.xml';

        return response()->streamDownload(
            fn () => print($export['xml']),
            $name,
            ['Content-Type' => 'application/xml']
        );
    }

    private function dateFrom(Request $request, string $key): string
    {
        return rescue(
            fn () => CarbonImmutable::parse($request->string($key)->toString() ?: 'today')->toDateString(),
            today()->toDateString(),
            false
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function monthFrom(Request $request): array
    {
        $month = $request->string('month')->toString();

        $lastMonth = fn () => CarbonImmutable::parse(today()->toDateString())->subMonth()->startOfMonth();

        $start = $month === ''
            ? $lastMonth()
            : rescue(fn () => CarbonImmutable::parse($month . '-01'), $lastMonth(), false);

        return [$start->startOfMonth()->toDateString(), $start->endOfMonth()->toDateString()];
    }
}
