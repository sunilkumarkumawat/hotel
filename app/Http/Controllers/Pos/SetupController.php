<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Pos\Outlet;
use App\Models\Pos\PosDepartment;
use App\Models\Pos\PosMenuCategory;
use App\Models\Pos\PosMenuItem;
use App\Models\Pos\PosNcType;
use App\Models\Pos\PosRatePlan;
use App\Models\Pos\PosReservationSlot;
use App\Models\Pos\PosSteward;
use App\Models\Pos\PosTable;
use Illuminate\View\View;


class SetupController extends Controller
{
    public function index(): View
    {
        $branch = \App\Helpers\Helper::getActiveBranchId();

        $cards = [
            [
                'label' => 'Outlets',
                'icon' => 'inbox',
                'route' => 'point-of-sale.setup.outlets',
                'permission' => 'point-of-sale/setup/outlets',
                'intro' => 'Every till: the restaurant, room service, the bar. Bill series, tax numbers and what prints on a receipt all live here.',
                'count' => Outlet::query()->forBranch($branch)->count(),
                'unit' => 'outlets',
            ],
            [
                'label' => 'Tables',
                'icon' => 'grid',
                'route' => 'point-of-sale.setup.tables',
                'permission' => 'point-of-sale/setup/tables',
                'intro' => 'The floor plan — sections, and the tables, villas or apartments inside them.',
                'count' => PosTable::query()->forBranch($branch)->count(),
                'unit' => 'tables',
            ],
            [
                'label' => 'Item Category',
                'icon' => 'layers',
                'route' => 'point-of-sale.setup.item-category',
                'permission' => 'point-of-sale/setup/item-category',
                'intro' => 'The headings on the menu, and the sub-headings under them.',
                'count' => PosMenuCategory::query()->forBranch($branch)->count(),
                'unit' => 'categories',
            ],
            [
                'label' => 'Items',
                'icon' => 'bag',
                'route' => 'point-of-sale.setup.items',
                'permission' => 'point-of-sale/setup/items',
                'intro' => 'The menu itself — what each thing is called, what it costs and which kitchen cooks it.',
                'count' => PosMenuItem::query()->forBranch($branch)->count(),
                'unit' => 'items',
            ],
            [
                'label' => 'Rate Plan',
                'icon' => 'credit-card',
                'route' => 'point-of-sale.setup.rate-plan',
                'permission' => 'point-of-sale/setup/rate-plan',
                'intro' => 'Price lists — à la carte, Happy Hours, banquet — and which outlets run them.',
                'count' => PosRatePlan::query()->forBranch($branch)->count(),
                'unit' => 'plans',
            ],
            [
                'label' => 'Department',
                'icon' => 'shield',
                'route' => 'point-of-sale.setup.department',
                'permission' => 'point-of-sale/setup/department',
                'intro' => 'Kitchen, Bar, Room Service. A KOT is routed by department.',
                'count' => PosDepartment::query()->forBranch($branch)->count(),
                'unit' => 'departments',
            ],
            [
                'label' => 'KOT Printing Setup',
                'icon' => 'file',
                'route' => null,
                'permission' => 'point-of-sale/setup/kot-printing',
                'intro' => 'Which printer each department’s tickets come out of. Not built yet.',
                'count' => null,
                'unit' => null,
            ],
            [
                'label' => 'Slots',
                'icon' => 'calendar',
                'route' => 'point-of-sale.setup.slots',
                'permission' => 'point-of-sale/setup/slots',
                'intro' => 'The sittings each outlet takes table bookings for, and how many it holds.',
                'count' => PosReservationSlot::query()->forBranch($branch)->count(),
                'unit' => 'slots',
            ],
            [
                'label' => 'Stewards',
                'icon' => 'user',
                'route' => 'point-of-sale.setup.stewards',
                'permission' => 'point-of-sale/setup/stewards',
                'intro' => 'Waiting staff. An order carries the name of whoever took it.',
                'count' => PosSteward::query()->forBranch($branch)->count(),
                'unit' => 'stewards',
            ],
            [
                'label' => 'NC Types',
                'icon' => 'alert',
                'route' => 'point-of-sale.setup.nc-types',
                'permission' => 'point-of-sale/setup/nc-types',
                'intro' => 'Reasons food goes out without being charged for — and who carries the cost.',
                'count' => PosNcType::query()->forBranch($branch)->count(),
                'unit' => 'types',
            ],
        ];

        return view('pos.setup.home', [
            'cards' => collect($cards)->filter(fn (array $card) => can_do($card['permission'], 'view'))->values(),
        ]);
    }
}
