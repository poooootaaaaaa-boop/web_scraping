<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Execution;
use App\Models\HistoryValue;
use App\Models\ScrapingElement;
use App\Models\ScrapingTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExecutionController extends Controller
{
    public function index(Request $request, string $project): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 50), 1), 100);
        return response()->json(Execution::where('project_id', $project)->with('task')->latest('started_at')->paginate($limit));
    }

    public function all(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 30), 1), 100);
        return response()->json(['data' => Execution::with(['task', 'project'])->latest('started_at')->paginate($limit)]);
    }

    public function byTask(Request $request, ScrapingTask $task): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 50), 1), 100);
        return response()->json(['data' => $task->executions()->with(['task', 'project'])->latest('started_at')->paginate($limit)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:80'],
            'project_id' => ['nullable', 'string', 'exists:projects,id'],
            'task_id' => ['nullable', 'string', 'exists:scraping_tasks,id'],
            'scheduled_at' => ['nullable', 'date'],
            'started_at' => ['required', 'date'],
        ]);
        $task = isset($data['task_id']) ? ScrapingTask::findOrFail($data['task_id']) : null;
        $projectId = $task?->project_id ?? ($data['project_id'] ?? null);
        validator(['project_id' => $projectId], ['project_id' => ['required', 'exists:projects,id']])->validate();
        $execution = Execution::create([
            'id' => $data['id'] ?? (string) Str::uuid(),
            'project_id' => $projectId,
            'task_id' => $task?->id,
            'scheduled_at' => $data['scheduled_at'] ?? now(),
            'started_at' => $data['started_at'],
            'duration_ms' => 0,
            'status' => 'running',
        ]);
        return response()->json(['data' => $execution], 201);
    }

    public function show(Execution $execution): JsonResponse
    {
        return response()->json(['data' => $execution->load(['task', 'historyValues'])]);
    }

    public function complete(Request $request, Execution $execution): JsonResponse
    {
        $data = $request->validate([
            'finished_at' => ['required', 'date'],
            'duration_ms' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:success,partial,failed,running'],
            'fields_processed' => ['sometimes', 'integer', 'min:0'],
            'fields_succeeded' => ['required', 'integer', 'min:0'],
            'fields_failed' => ['required', 'integer', 'min:0'],
            'error_message' => ['nullable', 'string'],
            ...$this->valueRules(),
        ]);
        $values = $data['values'] ?? [];
        unset($data['values']);
        $data['fields_processed'] = $data['fields_processed'] ?? $data['fields_succeeded'] + $data['fields_failed'];
        DB::transaction(function () use ($execution, $data, $values) {
            $this->persistValues($execution, $values);
            $execution->update($data);
        });
        return response()->json(['data' => $execution->fresh()]);
    }

    public function storeValues(Request $request, Execution $execution): JsonResponse
    {
        $data = $request->validate([...$this->valueRules(), 'values' => ['required', 'array', 'max:500']]);
        $rows = DB::transaction(function () use ($data, $execution) {
            $this->persistValues($execution, $data['values']);
            return $execution->historyValues()->orderBy('captured_at')->get();
        });
        return response()->json(['data' => $rows], 201);
    }

    public function values(Execution $execution): JsonResponse
    {
        $values = $execution->historyValues()->orderBy('captured_at')->get();
        return response()->json(['data' => $values]);
    }

    public function statistics(): JsonResponse
    {
        $stats = Execution::selectRaw('COUNT(*) as total, SUM(status = ?) as success_count, SUM(status = ?) as failure_count, AVG(CASE WHEN status = ? THEN duration_ms END) as avg_duration', ['success', 'failed', 'success'])->first();
        $total = (int) $stats->total;
        $success = (int) $stats->success_count;
        return response()->json(['data' => [
            'total' => $total,
            'successCount' => $success,
            'failureCount' => (int) $stats->failure_count,
            'avgDurationMs' => (float) ($stats->avg_duration ?? 0),
            'successRate' => $total ? $success / $total * 100 : 100,
        ]]);
    }

    private function valueRules(): array
    {
        return [
            'values' => ['sometimes', 'array', 'max:500'],
            'values.*.id' => ['required', 'string', 'max:80'],
            'values.*.element_id' => ['nullable', 'string', 'max:80'],
            'values.*.element_name' => ['nullable', 'string', 'max:255'],
            'values.*.data_type' => ['nullable', 'string', 'max:40'],
            'values.*.captured_at' => ['required', 'date'],
            'values.*.raw_value' => ['nullable', 'string'],
            'values.*.normalized_value' => ['nullable', 'string'],
            'values.*.numeric_value' => ['nullable', 'numeric'],
            'values.*.currency' => ['nullable', 'string', 'max:12'],
            'values.*.status' => ['required', 'in:valid,invalid,ambiguous,empty'],
            'values.*.error_message' => ['nullable', 'string'],
        ];
    }

    private function persistValues(Execution $execution, array $values): void
    {
        foreach ($values as $value) {
            $field = isset($value['element_id']) ? ScrapingElement::withTrashed()->find($value['element_id']) : null;
            HistoryValue::firstOrCreate(['id' => $value['id']], [
                'project_id' => $execution->project_id,
                'execution_id' => $execution->id,
                'element_id' => $field?->id,
                'element_name' => $value['element_name'] ?? $field?->name ?? 'Campo eliminado',
                'data_type' => $value['data_type'] ?? $field?->data_type ?? 'text',
                'raw_value' => $value['raw_value'] ?? '',
                'normalized_value' => $value['normalized_value'] ?? $value['raw_value'] ?? '',
                'numeric_value' => $value['numeric_value'] ?? null,
                'currency' => $value['currency'] ?? null,
                'status' => $value['status'],
                'captured_at' => $value['captured_at'],
                'error_message' => $value['error_message'] ?? null,
            ]);
        }
    }
}