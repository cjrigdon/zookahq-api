<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Accounting\ApBill;
use App\Models\Accounting\ArInvoice;
use App\Models\Booking\Lead;
use App\Models\Booking\Opportunity;
use App\Models\Events\Event;
use App\Models\Operations\WorkOrder;
use App\Services\Reporting\ReportCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class StandardReportController extends Controller
{
    use AuthorizesPermissions;

    public function overview(): JsonResponse
    {
        $this->authorizePermission('reporting.standard.view');

        $data = [
            'booking' => null,
            'events' => null,
            'operations' => null,
            'accounting' => null,
        ];

        if (Schema::hasTable('leads') && Schema::hasTable('opportunities')) {
            $data['booking'] = [
                'leads_total' => Lead::count(),
                'leads_by_status' => Lead::query()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status'),
                'opportunities_total' => Opportunity::count(),
                'pipeline_value' => (float) Opportunity::whereNotIn('stage', ['lost', 'cancelled'])->sum('value'),
                'opportunities_by_stage' => Opportunity::query()->selectRaw('stage, count(*) as count, coalesce(sum(value),0) as value')->groupBy('stage')->get(),
            ];
        }

        if (Schema::hasTable('events')) {
            $data['events'] = [
                'events_total' => Event::count(),
                'upcoming' => Event::where('starts_at', '>=', now())->count(),
                'by_status' => Event::query()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status'),
            ];
        }

        if (Schema::hasTable('work_orders')) {
            $data['operations'] = [
                'open' => WorkOrder::whereNotIn('status', ['completed', 'cancelled'])->count(),
                'by_priority' => WorkOrder::query()
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->selectRaw('priority, count(*) as count')
                    ->groupBy('priority')
                    ->pluck('count', 'priority'),
                'by_status' => WorkOrder::query()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status'),
            ];
        }

        if (Schema::hasTable('ar_invoices') && Schema::hasTable('ap_bills')) {
            $data['accounting'] = [
                'ar_open' => (float) ArInvoice::whereIn('status', ['sent', 'partial'])->sum('balance_due'),
                'ap_open' => (float) ApBill::whereIn('status', ['open', 'partial'])->sum('balance_due'),
                'invoices' => ArInvoice::count(),
                'bills' => ApBill::count(),
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function catalog(ReportCatalog $catalog): JsonResponse
    {
        $this->authorizePermission('reporting.custom.view');

        $datasets = collect($catalog->datasets())->map(fn ($d) => [
            'key' => $d['key'],
            'name' => $d['name'],
            'module' => $d['module'],
            'columns' => $d['columns'],
            'default_group_by' => $d['default_group_by'],
        ])->values();

        return response()->json(['data' => $datasets]);
    }
}
