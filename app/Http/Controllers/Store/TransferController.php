<?php

namespace App\Http\Controllers\Store;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreDoc;
use Illuminate\View\View;


class TransferController extends Controller
{
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
