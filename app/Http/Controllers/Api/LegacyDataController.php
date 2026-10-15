<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Execution;
use App\Models\HistoryValue;
use App\Models\Project;
use App\Models\ScrapingElement;
use App\Models\ScrapingTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LegacyDataController extends Controller
{
    public function import(Request $request, string $resource): JsonResponse
    {
        abort_unless(in_array($resource, ['projects', 'fields', 'tasks', 'executions', 'history'], true), 404);
        $items = $request->validate(['items' => ['required', 'array', 'max:250']])['items'];
        DB::transaction(function () use ($items, $resource) {
            foreach ($items as $item) {
                match ($resource) {
                    'projects' => $this->importProject($item),
                    'fields' => $this->importField($item),
                    'tasks' => $this->importTask($item),
                    'executions' => $this->importExecution($item),
                    'history' => $this->importHistoryValue($item),
                };
            }
        });
        return response()->json(['data' => ['imported' => count($items), 'resource' => $resource]]);
    }

    public function export(): JsonResponse
    {
        $projects = Project::withTrashed()->orderBy('created_at')->get();
        $fields = ScrapingElement::withTrashed()->orderBy('created_at')->get()->map(function ($field) {
            $row = $field->toArray();
            $row['attributes_json'] = json_encode($row['attributes'] ?? []);
            $row['is_active'] = (int) $field->is_active;
            unset($row['attributes']);
            return $row;
        });
        $tasks = ScrapingTask::withTrashed()->orderBy('created_at')->get()->map(function ($task) {
            $row = $task->toArray();
            $row['is_active'] = (int) $task->is_active;
            return $row;
        });
        $executions = Execution::orderBy('started_at')->get();
        $history = HistoryValue::orderBy('captured_at')->get()->map(function ($value) {
            $row = $value->toArray();
            $row['numeric_value'] = $value->numeric_value === null ? null : (float) $value->numeric_value;
            return $row;
        });
        return response()->json(['data' => [
            'version' => 2,
            'exported_at' => now()->toISOString(),
            'projects' => $projects,
            'scraping_elements' => $fields,
            'scraping_tasks' => $tasks,
            'executions' => $executions,
            'history_values' => $history,
        ]]);
    }

    private function importProject(array $row): void
    {
        validator($row, ['id' => ['required', 'string', 'max:80'], 'name' => ['required', 'string'], 'url' => ['required', 'url:http,https']])->validate();
        Project::withTrashed()->firstOrCreate(['id' => $row['id']], [
            'name' => $row['name'], 'url' => $row['url'], 'description' => $row['description'] ?? null,
            'status' => $row['status'] ?? 'active', 'config' => $row['config'] ?? null,
        ]);
    }

    private function importField(array $row): void
    {
        validator($row, ['id' => ['required', 'string', 'max:80'], 'project_id' => ['required', 'exists:projects,id'], 'name' => ['required', 'string'], 'selector_css' => ['required', 'string']])->validate();
        $attributes = $row['attributes'] ?? json_decode($row['attributes_json'] ?? '{}', true);
        ScrapingElement::withTrashed()->firstOrCreate(['id' => $row['id']], [
            'project_id' => $row['project_id'], 'name' => $row['name'], 'selector_css' => $row['selector_css'],
            'tag_name' => $row['tag_name'] ?? '', 'attributes' => is_array($attributes) ? $attributes : [],
            'extraction_method' => $row['extraction_method'] ?? 'visible_text',
            'attribute_name' => $row['attribute_name'] ?? null, 'data_type' => $row['data_type'] ?? 'text',
            'is_active' => (bool) ($row['is_active'] ?? true), 'config' => $row['config'] ?? null,
        ]);
    }

    private function importTask(array $row): void
    {
        validator($row, ['id' => ['required', 'string', 'max:80'], 'project_id' => ['required', 'exists:projects,id'], 'name' => ['required', 'string'], 'interval_value' => ['required', 'integer', 'min:1'], 'interval_unit' => ['required', 'in:minutes,hours,days']])->validate();
        $active = (bool) ($row['is_active'] ?? true);
        ScrapingTask::withTrashed()->firstOrCreate(['id' => $row['id']], [
            'project_id' => $row['project_id'], 'name' => $row['name'],
            'interval_value' => $row['interval_value'], 'interval_unit' => $row['interval_unit'],
            'status' => $active ? 'active' : 'paused', 'is_active' => $active,
            'last_run_at' => $row['last_run_at'] ?? null, 'next_run_at' => $row['next_run_at'] ?? null,
        ]);
    }

    private function importExecution(array $row): void
    {
        validator($row, ['id' => ['required', 'string', 'max:80'], 'task_id' => ['required', 'exists:scraping_tasks,id'], 'started_at' => ['required', 'date']])->validate();
        $task = ScrapingTask::withTrashed()->findOrFail($row['task_id']);
        Execution::firstOrCreate(['id' => $row['id']], [
            'project_id' => $task->project_id, 'task_id' => $task->id,
            'scheduled_at' => $row['scheduled_at'] ?? null, 'started_at' => $row['started_at'],
            'finished_at' => $row['finished_at'] ?? null, 'duration_ms' => $row['duration_ms'] ?? 0,
            'status' => $row['status'] ?? 'failed', 'fields_processed' => $row['fields_processed'] ?? 0,
            'fields_succeeded' => $row['fields_succeeded'] ?? 0, 'fields_failed' => $row['fields_failed'] ?? 0,
            'error_message' => $row['error_message'] ?? null,
        ]);
    }

    private function importHistoryValue(array $row): void
    {
        validator($row, ['id' => ['required', 'string', 'max:80'], 'execution_id' => ['required', 'exists:executions,id'], 'element_id' => ['required', 'string', 'max:80'], 'captured_at' => ['required', 'date']])->validate();
        $execution = Execution::findOrFail($row['execution_id']);
        $field = ScrapingElement::withTrashed()->find($row['element_id']);
        HistoryValue::firstOrCreate(['id' => $row['id']], [
            'project_id' => $execution->project_id, 'execution_id' => $execution->id,
            'element_id' => $field?->id, 'element_name' => $row['element_name'] ?? $field?->name ?? 'Campo eliminado',
            'data_type' => $row['data_type'] ?? $field?->data_type ?? 'text',
            'raw_value' => $row['raw_value'] ?? '', 'normalized_value' => $row['normalized_value'] ?? $row['raw_value'] ?? null,
            'numeric_value' => $row['numeric_value'] ?? null, 'currency' => $row['currency'] ?? null,
            'status' => $row['status'] ?? 'valid', 'captured_at' => $row['captured_at'],
            'error_message' => $row['error_message'] ?? null,
        ]);
    }
}