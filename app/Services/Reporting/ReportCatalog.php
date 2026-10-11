<?php

namespace App\Services\Reporting;

use App\Models\Accounting\ApBill;
use App\Models\Accounting\ArInvoice;
use App\Models\Booking\Lead;
use App\Models\Booking\Opportunity;
use App\Models\Events\Event;
use App\Models\Operations\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ReportCatalog
{
    public function datasets(): array
    {
        return array_values(array_filter([
            $this->definition('leads', 'Leads', 'booking', Lead::class, [
                ['key' => 'id', 'label' => 'ID', 'type' => 'number'],
                ['key' => 'first_name', 'label' => 'First name', 'type' => 'string'],
                ['key' => 'last_name', 'label' => 'Last name', 'type' => 'string'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'string'],
                ['key' => 'company', 'label' => 'Company', 'type' => 'string'],
                ['key' => 'source', 'label' => 'Source', 'type' => 'string'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
                ['key' => 'created_at', 'label' => 'Created', 'type' => 'datetime'],
            ], 'status'),
            $this->definition('opportunities', 'Opportunities', 'booking', Opportunity::class, [
                ['key' => 'id', 'label' => 'ID', 'type' => 'number'],
                ['key' => 'name', 'label' => 'Name', 'type' => 'string'],
                ['key' => 'stage', 'label' => 'Stage', 'type' => 'string'],
                ['key' => 'value', 'label' => 'Value', 'type' => 'money'],
                ['key' => 'venue_space', 'label' => 'Venue space', 'type' => 'string'],
                ['key' => 'expected_attendance', 'label' => 'Attendance', 'type' => 'number'],
                ['key' => 'event_start_date', 'label' => 'Event start', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'Created', 'type' => 'datetime'],
            ], 'stage'),
            $this->definition('events', 'Events', 'events', Event::class, [
                ['key' => 'id', 'label' => 'ID', 'type' => 'number'],
                ['key' => 'name', 'label' => 'Name', 'type' => 'string'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
                ['key' => 'venue_space', 'label' => 'Venue space', 'type' => 'string'],
                ['key' => 'expected_attendance', 'label' => 'Attendance', 'type' => 'number'],
                ['key' => 'starts_at', 'label' => 'Starts', 'type' => 'datetime'],
                ['key' => 'ends_at', 'label' => 'Ends', 'type' => 'datetime'],
            ], 'status'),
            $this->definition('work_orders', 'Work orders', 'operations', WorkOrder::class, [
                ['key' => 'id', 'label' => 'ID', 'type' => 'number'],
                ['key' => 'title', 'label' => 'Title', 'type' => 'string'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
                ['key' => 'priority', 'label' => 'Priority', 'type' => 'string'],
                ['key' => 'category', 'label' => 'Category', 'type' => 'string'],
                ['key' => 'location', 'label' => 'Location', 'type' => 'string'],
                ['key' => 'due_at', 'label' => 'Due', 'type' => 'datetime'],
                ['key' => 'created_at', 'label' => 'Created', 'type' => 'datetime'],
            ], 'status'),
            $this->definition('ar_invoices', 'AR invoices', 'accounting', ArInvoice::class, [
                ['key' => 'id', 'label' => 'ID', 'type' => 'number'],
                ['key' => 'invoice_number', 'label' => 'Invoice #', 'type' => 'string'],
                ['key' => 'bill_to_name', 'label' => 'Bill to', 'type' => 'string'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
                ['key' => 'total', 'label' => 'Total', 'type' => 'money'],
                ['key' => 'balance_due', 'label' => 'Balance due', 'type' => 'money'],
                ['key' => 'invoice_date', 'label' => 'Invoice date', 'type' => 'date'],
                ['key' => 'due_date', 'label' => 'Due date', 'type' => 'date'],
            ], 'status'),
            $this->definition('ap_bills', 'AP bills', 'accounting', ApBill::class, [
                ['key' => 'id', 'label' => 'ID', 'type' => 'number'],
                ['key' => 'bill_number', 'label' => 'Bill #', 'type' => 'string'],
                ['key' => 'vendor_name', 'label' => 'Vendor', 'type' => 'string'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
                ['key' => 'total', 'label' => 'Total', 'type' => 'money'],
                ['key' => 'balance_due', 'label' => 'Balance due', 'type' => 'money'],
                ['key' => 'bill_date', 'label' => 'Bill date', 'type' => 'date'],
                ['key' => 'due_date', 'label' => 'Due date', 'type' => 'date'],
            ], 'status'),
        ]));
    }

    public function find(string $dataset): ?array
    {
        foreach ($this->datasets() as $item) {
            if ($item['key'] === $dataset) {
                return $item;
            }
        }

        return null;
    }

    public function run(string $dataset, array $columns, ?array $filters = null, ?string $sortColumn = null, string $sortDirection = 'desc', ?string $groupBy = null, int $limit = 200): array
    {
        $def = $this->find($dataset);
        if (! $def) {
            abort(422, 'Unknown dataset.');
        }

        $allowed = collect($def['columns'])->pluck('key')->all();
        $columns = array_values(array_intersect($columns ?: $allowed, $allowed));
        if ($columns === []) {
            $columns = $allowed;
        }

        /** @var Builder $query */
        $query = ($def['model'])::query();

        foreach ($filters ?? [] as $filter) {
            $field = $filter['field'] ?? null;
            $op = $filter['op'] ?? 'eq';
            $value = $filter['value'] ?? null;
            if (! $field || ! in_array($field, $allowed, true)) {
                continue;
            }
            match ($op) {
                'contains' => $query->where($field, 'like', '%'.$value.'%'),
                'gt' => $query->where($field, '>', $value),
                'gte' => $query->where($field, '>=', $value),
                'lt' => $query->where($field, '<', $value),
                'lte' => $query->where($field, '<=', $value),
                'neq' => $query->where($field, '!=', $value),
                default => $query->where($field, $value),
            };
        }

        if ($sortColumn && in_array($sortColumn, $allowed, true)) {
            $query->orderBy($sortColumn, strtolower($sortDirection) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest('id');
        }

        $rows = $query->limit(min($limit, 500))->get($columns);

        $grouped = null;
        if ($groupBy && in_array($groupBy, $allowed, true)) {
            $grouped = $rows->groupBy($groupBy)->map(function (Collection $g, $key) {
                $sample = $g->first();

                return [
                    'key' => (string) $key,
                    'label' => (string) $key,
                    'count' => $g->count(),
                    'sum_value' => $sample && isset($sample->value) ? round((float) $g->sum('value'), 2) : null,
                    'sum_total' => $sample && isset($sample->total) ? round((float) $g->sum('total'), 2) : null,
                    'sum_balance_due' => $sample && isset($sample->balance_due) ? round((float) $g->sum('balance_due'), 2) : null,
                ];
            })->values()->all();
        }

        return [
            'dataset' => $dataset,
            'columns' => collect($def['columns'])->whereIn('key', $columns)->values()->all(),
            'rows' => $rows->map(fn ($row) => collect($columns)->mapWithKeys(fn ($c) => [$c => $row->{$c}]))->all(),
            'grouped' => $grouped,
            'meta' => [
                'count' => $rows->count(),
                'truncated' => $limit,
            ],
        ];
    }

    protected function definition(string $key, string $name, string $module, string $model, array $columns, ?string $defaultGroup): ?array
    {
        $table = (new $model)->getTable();
        if (! Schema::hasTable($table)) {
            return null;
        }

        return [
            'key' => $key,
            'name' => $name,
            'module' => $module,
            'model' => $model,
            'columns' => $columns,
            'default_group_by' => $defaultGroup,
        ];
    }
}
