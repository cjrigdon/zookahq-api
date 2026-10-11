<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'booking.leads.view', 'booking.leads.manage',
            'booking.accounts.view', 'booking.accounts.manage',
            'booking.contacts.view', 'booking.contacts.manage',
            'booking.opportunities.view', 'booking.opportunities.manage',
            'booking.proposals.view', 'booking.proposals.manage',
            'booking.contracts.view', 'booking.contracts.manage',
            'booking.sales_goals.view', 'booking.sales_goals.manage',
            'events.calendar.view',
            'events.events.view', 'events.events.manage',
            'events.functions.view', 'events.functions.manage',
            'events.service_orders.view', 'events.service_orders.manage',
            'events.changes.view',
            'operations.work_orders.view', 'operations.work_orders.manage',
            'accounting.inventory.view', 'accounting.inventory.manage',
            'accounting.ar_invoices.view', 'accounting.ar_invoices.manage',
            'accounting.ar_payments.view', 'accounting.ar_payments.manage',
            'accounting.budgets.view', 'accounting.budgets.manage',
            'accounting.gl.view', 'accounting.gl.manage',
            'accounting.ap_bills.view', 'accounting.ap_bills.manage',
            'accounting.ap_payments.view', 'accounting.ap_payments.manage',
            'accounting.reports.view',
            'reporting.standard.view',
            'reporting.custom.view', 'reporting.custom.manage',
            'reporting.ai.use',
            'admin.users.manage', 'admin.settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $tenantAdmin = Role::findOrCreate('tenant_admin');
        $tenantAdmin->syncPermissions($permissions);

        $sales = Role::findOrCreate('sales');
        $sales->syncPermissions([
            'booking.leads.view', 'booking.leads.manage',
            'booking.accounts.view', 'booking.accounts.manage',
            'booking.contacts.view', 'booking.contacts.manage',
            'booking.opportunities.view', 'booking.opportunities.manage',
            'booking.proposals.view', 'booking.proposals.manage',
            'booking.contracts.view', 'booking.contracts.manage',
            'booking.sales_goals.view', 'booking.sales_goals.manage',
            'events.calendar.view',
            'events.events.view', 'events.events.manage',
            'events.functions.view', 'events.functions.manage',
            'events.service_orders.view', 'events.service_orders.manage',
            'events.changes.view',
            'accounting.ar_invoices.view', 'accounting.ar_payments.view',
            'accounting.reports.view',
            'reporting.standard.view', 'reporting.custom.view', 'reporting.ai.use',
        ]);

        $operations = Role::findOrCreate('operations');
        $operations->syncPermissions([
            'events.calendar.view', 'events.events.view', 'events.functions.view',
            'events.service_orders.view', 'events.service_orders.manage', 'events.changes.view',
            'operations.work_orders.view', 'operations.work_orders.manage',
        ]);

        $accounting = Role::findOrCreate('accounting');
        $accounting->syncPermissions([
            'accounting.inventory.view', 'accounting.inventory.manage',
            'accounting.ar_invoices.view', 'accounting.ar_invoices.manage',
            'accounting.ar_payments.view', 'accounting.ar_payments.manage',
            'accounting.budgets.view', 'accounting.budgets.manage',
            'accounting.gl.view', 'accounting.gl.manage',
            'accounting.ap_bills.view', 'accounting.ap_bills.manage',
            'accounting.ap_payments.view', 'accounting.ap_payments.manage',
            'accounting.reports.view',
            'reporting.standard.view', 'reporting.custom.view', 'reporting.custom.manage', 'reporting.ai.use',
        ]);

        $readOnly = Role::findOrCreate('read_only');
        $readOnly->syncPermissions([
            'booking.leads.view', 'booking.accounts.view', 'booking.contacts.view',
            'booking.opportunities.view', 'booking.proposals.view', 'booking.contracts.view',
            'booking.sales_goals.view',
            'events.calendar.view', 'events.events.view', 'events.functions.view',
            'events.service_orders.view', 'events.changes.view',
            'operations.work_orders.view',
            'accounting.inventory.view', 'accounting.ar_invoices.view',
            'accounting.ar_payments.view', 'accounting.budgets.view',
            'accounting.gl.view', 'accounting.ap_bills.view',
            'accounting.ap_payments.view', 'accounting.reports.view',
            'reporting.standard.view', 'reporting.custom.view',
        ]);
    }
}
