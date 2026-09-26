<?php

namespace Database\Seeders;

use App\Models\Branch\Branch;
use App\Models\City\City;
use App\Models\Common\Module;
use App\Models\Common\SubModule;
use App\Models\Country\Country;
use App\Models\Role\Role;
use App\Models\State\State;
use App\Models\User;
use App\Models\UserPermission\UserPermission;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->location();
        $this->call(MenuSeeder::class);

        $branch = Branch::firstOrCreate(
            ['branch_code' => 'HO'],
            [
                'branch_name' => 'Head Office',
                'director_administrator' => 'Administrator',
                'mobile_number' => '9876543210',
                'email' => 'admin@example.com',
                'address' => 'Main Road',
                'country_id' => Country::where('name', 'India')->value('id'),
                'state_id' => State::where('name', 'Rajasthan')->value('id'),
                'city_id' => City::where('name', 'Jaipur')->value('id'),
                'pin_code' => '302001',
                'business_type' => 'Hotel',
                'status' => 1,
            ]
        );

        $admin = Role::firstOrCreate(['id' => 1], [
            'name' => 'Administrator',
            'description' => 'Full access to every screen, including users, roles and branches.',
            'branch_id' => null,
        ]);

        Role::firstOrCreate(['name' => 'Manager'], [
            'description' => 'Day-to-day operations, no access to roles or branches.',
            'branch_id' => $branch->id,
        ]);

        $staff = Role::firstOrCreate(['name' => 'Staff'], [
            'description' => 'Limited access — give them exactly what they need.',
            'branch_id' => $branch->id,
        ]);

        $user = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'mobile' => '9876543210',
                'password' => 'password',
                'role_id' => $admin->id,
                'branch_id' => $branch->id,
                'status' => 1,
                'is_verified' => 1,
            ]
        );

        (new MasterSeeder)->run($branch->id);

        // A standard Indian chart of accounts, so the Accounts module opens
        // with something in it. Safe to run again — it matches on branch+name.
        $this->call(AccountingSeeder::class);

        // The administrator bypasses permission checks, but give them a full
        // row anyway so the matrix opens pre-ticked.
        $submodules = SubModule::pluck('module_id', 'id');

        UserPermission::updateOrCreate(
            ['user_id' => $user->user_id, 'branch_id' => $branch->id],
            [
                'module_id' => $submodules->unique()->values()->implode(','),
                'submodule_id' => $submodules->keys()->implode(','),
                'permissions' => $submodules->keys()->mapWithKeys(fn ($id) => [
                    (string) $id => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 1],
                ])->all(),
            ]
        );

        $this->housekeepers($branch->id, $staff->id);

        $this->command?->info('Menu: ' . Module::count() . ' modules, ' . SubModule::count() . ' sub-modules');
        $this->command?->info('Login with  admin  /  password');
    }

    /**
     * Two cleaners, so House Keeping Status has somebody to assign rooms to.
     *
     * They get *only* House Keeping Status, which also makes them the account
     * to log in as when you want to see what a limited user sees — everything
     * else disappears from their sidebar on its own.
     */
    private function housekeepers(int $branchId, int $roleId): void
    {
        $allowed = SubModule::whereIn('url', [
            'house-keeping/board',
            'house-keeping/status',
            'front-office/room-calendar',
        ])->pluck('module_id', 'id');

        foreach ([
            ['Meena Devi', 'meena', '9876500011'],
            ['Ramesh Yadav', 'ramesh', '9876500012'],
        ] as [$name, $username, $mobile]) {
            $user = User::firstOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'mobile' => $mobile,
                    'password' => 'password',
                    'role_id' => $roleId,
                    'branch_id' => $branchId,
                    'status' => 1,
                    'is_verified' => 1,
                ]
            );

            UserPermission::updateOrCreate(
                ['user_id' => $user->user_id, 'branch_id' => $branchId],
                [
                    'module_id' => $allowed->unique()->values()->implode(','),
                    'submodule_id' => $allowed->keys()->implode(','),
                    // View and edit only: a cleaner marks rooms, and adds or
                    // deletes nothing.
                    'permissions' => $allowed->keys()->mapWithKeys(fn ($id) => [
                        (string) $id => ['view' => 1, 'edit' => 1],
                    ])->all(),
                ]
            );
        }
    }

    /** Just enough geography for the branch form to work out of the box. */
    private function location(): void
    {
        $india = Country::firstOrCreate(['name' => 'India'], ['iso2' => 'IN', 'phonecode' => '91']);

        $states = [
            'Rajasthan' => ['Jaipur', 'Jodhpur', 'Udaipur', 'Kota', 'Ajmer'],
            'Maharashtra' => ['Mumbai', 'Pune', 'Nagpur', 'Nashik'],
            'Delhi' => ['New Delhi', 'Dwarka', 'Rohini'],
            'Gujarat' => ['Ahmedabad', 'Surat', 'Vadodara'],
            'Karnataka' => ['Bengaluru', 'Mysuru', 'Mangaluru'],
        ];

        foreach ($states as $stateName => $cities) {
            $state = State::firstOrCreate(['name' => $stateName, 'country_id' => $india->id]);

            foreach ($cities as $cityName) {
                City::firstOrCreate(['name' => $cityName, 'state_id' => $state->id]);
            }
        }
    }
}
