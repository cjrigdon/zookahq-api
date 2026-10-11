<?php

namespace App\Services\Reporting;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AiReportAssistant
{
    public function __construct(protected ReportCatalog $catalog) {}

    public function suggest(string $prompt): array
    {
        $apiKey = config('reporting.openai_api_key');
        $allowStub = (bool) config('reporting.allow_stub', true);

        if ($apiKey) {
            try {
                return $this->suggestWithOpenAi($prompt, $apiKey);
            } catch (\Throwable $e) {
                if (! $allowStub) {
                    throw $e;
                }
            }
        }

        if (! $allowStub) {
            abort(422, 'AI reporting is not configured. Set OPENAI_API_KEY or enable REPORTING_AI_ALLOW_STUB.');
        }

        return $this->suggestStub($prompt);
    }

    protected function suggestWithOpenAi(string $prompt, string $apiKey): array
    {
        $datasets = collect($this->catalog->datasets())->map(fn ($d) => [
            'key' => $d['key'],
            'name' => $d['name'],
            'columns' => collect($d['columns'])->pluck('key')->all(),
        ])->values()->all();

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('reporting.openai_model', 'gpt-4o-mini'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You design CRM report configs. Reply JSON only with keys: name, description, dataset, columns (array), filters (array of {field,op,value}), group_by, sort_column, sort_direction, chart_type. Use only provided datasets/columns. ops: eq,neq,contains,gt,gte,lt,lte. chart_type: none,bar,pie,line.',
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode(['prompt' => $prompt, 'datasets' => $datasets], JSON_THROW_ON_ERROR),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI request failed: '.$response->body());
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        $parsed = json_decode((string) $content, true, 512, JSON_THROW_ON_ERROR);

        return $this->normalizeSuggestion($parsed, $prompt, 'openai');
    }

    protected function suggestStub(string $prompt): array
    {
        $lower = Str::lower($prompt);
        $dataset = 'opportunities';
        $chart = 'bar';
        $groupBy = 'stage';
        $columns = ['name', 'stage', 'value', 'event_start_date'];
        $filters = [];
        $name = 'AI suggested pipeline report';

        if (Str::contains($lower, ['lead', 'prospect'])) {
            $dataset = 'leads';
            $groupBy = 'status';
            $columns = ['first_name', 'last_name', 'company', 'status', 'source'];
            $name = 'AI suggested leads report';
        } elseif (Str::contains($lower, ['invoice', 'receivable', 'ar '])) {
            $dataset = 'ar_invoices';
            $groupBy = 'status';
            $columns = ['invoice_number', 'bill_to_name', 'status', 'total', 'balance_due', 'due_date'];
            $name = 'AI suggested AR report';
            $chart = 'pie';
        } elseif (Str::contains($lower, ['bill', 'payable', 'vendor', 'ap '])) {
            $dataset = 'ap_bills';
            $groupBy = 'status';
            $columns = ['bill_number', 'vendor_name', 'status', 'total', 'balance_due'];
            $name = 'AI suggested AP report';
            $chart = 'pie';
        } elseif (Str::contains($lower, ['work order', 'ops', 'maintenance', 'workorder'])) {
            $dataset = 'work_orders';
            $groupBy = 'status';
            $columns = ['title', 'status', 'priority', 'category', 'due_at'];
            $name = 'AI suggested work orders report';
        } elseif (Str::contains($lower, ['event', 'calendar', 'function'])) {
            $dataset = 'events';
            $groupBy = 'status';
            $columns = ['name', 'status', 'venue_space', 'starts_at', 'expected_attendance'];
            $name = 'AI suggested events report';
        }

        if (Str::contains($lower, ['open', 'unpaid', 'balance'])) {
            $filters[] = ['field' => 'status', 'op' => 'neq', 'value' => 'paid'];
        }
        if (Str::contains($lower, ['qualified'])) {
            $filters[] = ['field' => 'status', 'op' => 'eq', 'value' => 'qualified'];
        }
        if (Str::contains($lower, ['booked', 'won'])) {
            $filters[] = ['field' => 'stage', 'op' => 'eq', 'value' => 'booked'];
        }

        return $this->normalizeSuggestion([
            'name' => $name,
            'description' => 'Generated locally from your prompt (AI stub mode).',
            'dataset' => $dataset,
            'columns' => $columns,
            'filters' => $filters,
            'group_by' => $groupBy,
            'sort_column' => in_array('value', $columns, true) ? 'value' : ($columns[0] ?? 'id'),
            'sort_direction' => 'desc',
            'chart_type' => $chart,
        ], $prompt, 'stub');
    }

    protected function normalizeSuggestion(array $parsed, string $prompt, string $provider): array
    {
        $datasetKey = $parsed['dataset'] ?? 'opportunities';
        $def = $this->catalog->find($datasetKey) ?? $this->catalog->datasets()[0] ?? null;
        if (! $def) {
            abort(422, 'No reportable datasets available.');
        }

        $allowed = collect($def['columns'])->pluck('key')->all();
        $columns = array_values(array_intersect($parsed['columns'] ?? $allowed, $allowed));
        if ($columns === []) {
            $columns = array_slice($allowed, 0, 5);
        }

        $filters = [];
        foreach ($parsed['filters'] ?? [] as $filter) {
            $field = $filter['field'] ?? null;
            if ($field && in_array($field, $allowed, true)) {
                $filters[] = [
                    'field' => $field,
                    'op' => $filter['op'] ?? 'eq',
                    'value' => $filter['value'] ?? null,
                ];
            }
        }

        $groupBy = $parsed['group_by'] ?? null;
        if ($groupBy && ! in_array($groupBy, $allowed, true)) {
            $groupBy = $def['default_group_by'] ?? null;
        }

        $sort = $parsed['sort_column'] ?? null;
        if ($sort && ! in_array($sort, $allowed, true)) {
            $sort = $columns[0] ?? null;
        }

        $chart = $parsed['chart_type'] ?? 'bar';
        if (! in_array($chart, ['none', 'bar', 'pie', 'line'], true)) {
            $chart = 'bar';
        }

        return [
            'provider' => $provider,
            'prompt' => $prompt,
            'suggestion' => [
                'name' => $parsed['name'] ?? ('Report: '.$def['name']),
                'description' => $parsed['description'] ?? null,
                'dataset' => $def['key'],
                'columns' => $columns,
                'filters' => $filters,
                'group_by' => $groupBy,
                'sort_column' => $sort,
                'sort_direction' => strtolower($parsed['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
                'chart_type' => $chart,
            ],
        ];
    }
}
