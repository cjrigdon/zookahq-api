<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['code' => 'booking', 'name' => 'Booking', 'description' => 'Leads, accounts, opportunities, proposals, contracts, sales goals', 'sort_order' => 1],
            ['code' => 'events', 'name' => 'Event Management', 'description' => 'Calendar, functions, service orders, change tracking', 'sort_order' => 2],
            ['code' => 'operations', 'name' => 'Operations', 'description' => 'Work order management', 'sort_order' => 3],
            ['code' => 'accounting', 'name' => 'Accounting', 'description' => 'Inventory, AR/AP, GL, payments', 'sort_order' => 4],
            ['code' => 'exhibitors', 'name' => 'Exhibitors', 'description' => 'Exhibitor portal, floor plans, registration', 'sort_order' => 5],
            ['code' => 'registration', 'name' => 'Registration', 'description' => 'Event registration, waitlisting, check-in', 'sort_order' => 6],
            ['code' => 'reporting', 'name' => 'Reporting', 'description' => 'Reports and AI-driven custom report building', 'sort_order' => 7],
        ];

        foreach ($modules as $module) {
            Module::query()->updateOrCreate(
                ['code' => $module['code']],
                $module + ['billable' => true, 'is_active' => true]
            );
        }
    }
}
