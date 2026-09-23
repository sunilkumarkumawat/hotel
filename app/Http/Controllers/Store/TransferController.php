<?php

namespace App\Http\Controllers\Store;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreDoc;
use Illuminate\View\View;

/**
 * What is on its way here from another outlet.
 *
 * Everything else in the Store module is reached by first picking a kind and
 * then, inside it, a document. Receiving a transfer starts from the other
 * end on purpose: nobody at the receiving outlet knows the transfer's
 * document number yet, only that something is due from another branch — so
 * this is a worklist of what other branches have sent this one, each row a
 * click away from becoming the transfer_in that puts it on the shelf.
 * DocController::create() redirects here whenever "New Transfer In" is
 * opened without already saying which transfer it is receiving against.
 */
class TransferController extends Controller
{
    /** GET store/transfer/inbox */
    public function inbox(): View
    {
        abort_unless(can_do('store/transfer', 'view'), 403);

        $branchId = (int) Helper::getActiveBranchId();

        $incoming = StoreDoc::ofKind('transfer_out')
            ->where('to_branch_id', $branchId)
            ->whereIn('status', ['posted', 'partial'])
            ->with(['branch', 'items'])
            ->orderByDesc('doc_date')
            ->orderByDesc('id')
            ->get();

        return view('store.transfer-inbox', [
            'incoming' => $incoming,
        ]);
    }
}
