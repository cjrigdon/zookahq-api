<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\TenantModule;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ModuleSeeder::class);

        PlatformUser::query()->firstOrCreate(
            ['email' => 'admin@zookahq.test'],
            [
                'name' => 'Platform Admin',
                'password' => 'password',
            ]
        );

        if (! Tenant::query()->where('slug', 'demo')->exists()) {
            $tenant = Tenant::create([
                'id' => 'demo',
                'name' => 'Demo Venue',
                'slug' => 'demo',
                'status' => 'active',
            ]);

            $tenant->domains()->create(['domain' => 'demo.localhost']);

            $moduleCodes = ['booking', 'events', 'operations', 'accounting', 'reporting'];
            foreach ($moduleCodes as $code) {
                $module = Module::where('code', $code)->first();
                if ($module) {
                    TenantModule::create([
                        'tenant_id' => $tenant->id,
                        'module_id' => $module->id,
                        'enabled' => true,
                        'enabled_at' => now(),
                    ]);
                }
            }

            $seeder = $this;
            $tenant->run(function () use ($seeder) {
                $user = \App\Models\User::create([
                    'name' => 'Demo Admin',
                    'email' => 'admin@demo.test',
                    'password' => 'password',
                ]);
                $user->assignRole('tenant_admin');

                $sales = \App\Models\User::create([
                    'name' => 'Demo Sales',
                    'email' => 'sales@demo.test',
                    'password' => 'password',
                ]);
                $sales->assignRole('sales');

                $ops = \App\Models\User::create([
                    'name' => 'Demo Ops',
                    'email' => 'ops@demo.test',
                    'password' => 'password',
                ]);
                $ops->assignRole('operations');

                $acct = \App\Models\User::create([
                    'name' => 'Demo Accounting',
                    'email' => 'accounting@demo.test',
                    'password' => 'password',
                ]);
                $acct->assignRole('accounting');

                $seeder->seedBookingSample($user);
                $seeder->seedEventsAndOpsSample($user);
                $seeder->seedAccountingSample($user);
                $seeder->seedReportingSample($user);
            });
        }
    }

    public function seedBookingSample($user): void
    {
        \App\Models\TenantSetting::create([
            'company_name' => 'Demo Venue',
            'timezone' => 'America/Chicago',
            'locale' => 'en',
            'currency' => 'USD',
        ]);

        $lead = \App\Models\Booking\Lead::create([
            'first_name' => 'Jordan',
            'last_name' => 'Lee',
            'email' => 'jordan@acmeevents.test',
            'company' => 'Acme Events',
            'source' => 'website',
            'status' => 'qualified',
            'owner_user_id' => $user->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $account = \App\Models\Booking\Account::create([
            'name' => 'Acme Events',
            'email' => 'jordan@acmeevents.test',
            'phone' => '555-0100',
            'city' => 'Austin',
            'state' => 'TX',
            'owner_user_id' => $user->id,
            'converted_from_lead_id' => $lead->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $contact = \App\Models\Booking\Contact::create([
            'account_id' => $account->id,
            'first_name' => 'Jordan',
            'last_name' => 'Lee',
            'email' => 'jordan@acmeevents.test',
            'is_primary' => true,
            'converted_from_lead_id' => $lead->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $opportunity = \App\Models\Booking\Opportunity::create([
            'name' => 'Acme Annual Gala',
            'account_id' => $account->id,
            'contact_id' => $contact->id,
            'stage' => 'proposal',
            'value' => 45000,
            'event_start_date' => now()->addMonths(2)->toDateString(),
            'event_end_date' => now()->addMonths(2)->addDay()->toDateString(),
            'venue_space' => 'Grand Ballroom',
            'expected_attendance' => 350,
            'owner_user_id' => $user->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        \App\Models\Booking\Proposal::create([
            'opportunity_id' => $opportunity->id,
            'title' => 'Acme Gala Proposal',
            'version' => 1,
            'status' => 'sent',
            'amount' => 45000,
            'valid_until' => now()->addWeeks(2)->toDateString(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        \App\Models\Booking\SalesGoal::create([
            'name' => 'Q Sales Target',
            'period_type' => 'quarter',
            'period_start' => now()->startOfQuarter()->toDateString(),
            'period_end' => now()->endOfQuarter()->toDateString(),
            'target_amount' => 250000,
            'owner_user_id' => $user->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function seedEventsAndOpsSample($user): void
    {
        $event = \App\Models\Events\Event::create([
            'name' => 'Acme Annual Gala',
            'status' => 'confirmed',
            'starts_at' => now()->addMonths(2)->setTime(17, 0),
            'ends_at' => now()->addMonths(2)->addDay()->setTime(23, 0),
            'venue_space' => 'Grand Ballroom',
            'expected_attendance' => 350,
            'owner_user_id' => $user->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $reception = \App\Models\Events\EventFunction::create([
            'event_id' => $event->id,
            'name' => 'Cocktail Reception',
            'function_type' => 'reception',
            'status' => 'scheduled',
            'starts_at' => now()->addMonths(2)->setTime(17, 0),
            'ends_at' => now()->addMonths(2)->setTime(19, 0),
            'room' => 'Foyer',
            'guaranteed_count' => 300,
            'set_count' => 350,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        \App\Models\Events\EventFunction::create([
            'event_id' => $event->id,
            'name' => 'Dinner Service',
            'function_type' => 'meal',
            'status' => 'scheduled',
            'starts_at' => now()->addMonths(2)->setTime(19, 0),
            'ends_at' => now()->addMonths(2)->setTime(22, 0),
            'room' => 'Grand Ballroom',
            'guaranteed_count' => 320,
            'set_count' => 350,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        \App\Models\Events\ServiceOrder::create([
            'event_id' => $event->id,
            'event_function_id' => $reception->id,
            'title' => 'Bar Setup & Beverage Package',
            'status' => 'issued',
            'service_type' => 'beverage',
            'service_at' => now()->addMonths(2)->setTime(16, 0),
            'amount' => 8500,
            'instructions' => 'Full hosted bar with signature cocktail.',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        \App\Models\Events\EventChange::create([
            'event_id' => $event->id,
            'entity_type' => 'event',
            'entity_id' => $event->id,
            'change_type' => 'create',
            'summary' => 'Event created from booking pipeline',
            'changed_by' => $user->id,
        ]);

        \App\Models\Operations\WorkOrder::create([
            'title' => 'Ballroom AV check',
            'status' => 'open',
            'priority' => 'high',
            'category' => 'av',
            'location' => 'Grand Ballroom',
            'event_id' => $event->id,
            'due_at' => now()->addMonths(2)->subDay()->setTime(10, 0),
            'description' => 'Verify projectors, mics, and stage lighting for gala.',
            'assignee_user_id' => $user->id,
            'requested_by' => $user->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        \App\Models\Operations\WorkOrder::create([
            'title' => 'Replace foyer carpet tile',
            'status' => 'in_progress',
            'priority' => 'normal',
            'category' => 'facilities',
            'location' => 'Main Foyer',
            'due_at' => now()->addWeek(),
            'description' => 'Damaged tile near east entrance.',
            'assignee_user_id' => $user->id,
            'requested_by' => $user->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function seedAccountingSample($user): void
    {
        $cash = \App\Models\Accounting\GlAccount::firstOrCreate(
            ['code' => '1000'],
            ['name' => 'Cash', 'type' => 'asset', 'is_active' => true]
        );
        $ar = \App\Models\Accounting\GlAccount::firstOrCreate(
            ['code' => '1100'],
            ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]
        );
        $revenue = \App\Models\Accounting\GlAccount::firstOrCreate(
            ['code' => '4000'],
            ['name' => 'Event Revenue', 'type' => 'revenue', 'is_active' => true]
        );
        $expense = \App\Models\Accounting\GlAccount::firstOrCreate(
            ['code' => '5000'],
            ['name' => 'Operating Expense', 'type' => 'expense', 'is_active' => true]
        );
        $ap = \App\Models\Accounting\GlAccount::firstOrCreate(
            ['code' => '2000'],
            ['name' => 'Accounts Payable', 'type' => 'liability', 'is_active' => true]
        );

        $linen = \App\Models\Accounting\InventoryItem::create([
            'sku' => 'LINEN-90',
            'name' => '90" Round Table Linen',
            'category' => 'linen',
            'unit' => 'each',
            'quantity_on_hand' => 120,
            'reorder_level' => 40,
            'unit_cost' => 18.5,
            'unit_price' => 35,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $invoice = \App\Models\Accounting\ArInvoice::create([
            'invoice_number' => 'INV-DEMO-001',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'bill_to_name' => 'Acme Events',
            'bill_to_email' => 'jordan@acmeevents.test',
            'tax_total' => 0,
            'notes' => 'Deposit invoice for Acme Annual Gala',
            'portal_token' => 'demo-portal-token-acme-gala',
            'portal_token_expires_at' => now()->addDays(60),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        \App\Models\Accounting\ArInvoiceLine::create([
            'ar_invoice_id' => $invoice->id,
            'inventory_item_id' => $linen->id,
            'description' => 'Ballroom rental deposit',
            'quantity' => 1,
            'unit_price' => 10000,
            'line_total' => 10000,
            'sort_order' => 0,
        ]);
        \App\Models\Accounting\ArInvoiceLine::create([
            'ar_invoice_id' => $invoice->id,
            'inventory_item_id' => $linen->id,
            'description' => 'Linen package (estimate)',
            'quantity' => 40,
            'unit_price' => 35,
            'line_total' => 1400,
            'sort_order' => 1,
        ]);
        $invoice->recalculateTotals();

        \App\Models\Accounting\ArBudget::create([
            'name' => 'Event Revenue Budget',
            'period_type' => 'quarter',
            'period_start' => now()->startOfQuarter()->toDateString(),
            'period_end' => now()->endOfQuarter()->toDateString(),
            'gl_account_id' => $revenue->id,
            'budget_amount' => 250000,
            'actual_amount' => 45000,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $bill = \App\Models\Accounting\ApBill::create([
            'bill_number' => 'BILL-DEMO-001',
            'vendor_name' => 'City Produce Co',
            'vendor_email' => 'ap@cityproduce.test',
            'status' => 'open',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(21)->toDateString(),
            'tax_total' => 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        \App\Models\Accounting\ApBillLine::create([
            'ap_bill_id' => $bill->id,
            'gl_account_id' => $expense->id,
            'description' => 'Catering produce order',
            'quantity' => 1,
            'unit_cost' => 2750,
            'line_total' => 2750,
        ]);
        $bill->recalculateTotals();

        // silence unused vars for static analysis
        unset($cash, $ar, $ap);
    }

    public function seedReportingSample($user): void
    {
        \App\Models\Reporting\SavedReport::create([
            'name' => 'Pipeline by stage',
            'description' => 'Opportunity value grouped by sales stage',
            'dataset' => 'opportunities',
            'columns' => ['name', 'stage', 'value', 'event_start_date', 'venue_space'],
            'filters' => [],
            'sort_column' => 'value',
            'sort_direction' => 'desc',
            'chart_type' => 'bar',
            'group_by' => 'stage',
            'is_favorite' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        \App\Models\Reporting\SavedReport::create([
            'name' => 'Open AR invoices',
            'description' => 'Client invoices with remaining balances',
            'dataset' => 'ar_invoices',
            'columns' => ['invoice_number', 'bill_to_name', 'status', 'total', 'balance_due', 'due_date'],
            'filters' => [
                ['field' => 'status', 'op' => 'neq', 'value' => 'paid'],
            ],
            'sort_column' => 'due_date',
            'sort_direction' => 'asc',
            'chart_type' => 'pie',
            'group_by' => 'status',
            'is_favorite' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}
