<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ScrapingTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    public function all(): JsonResponse
    {
        return response()->json(['data' => ScrapingTask::with(['project'])->withCount('executions')->latest()->paginate(100)]);
    }

    public function index(Project $project): JsonResponse
    {
        return response()->json(['data' => $project->tasks()->withCount('executions')->latest()->get()]);
    }

    public function show(ScrapingTask $task): JsonResponse
    {
        return response()->json(['data' => $task->load('project')->loadCount('executions')]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $this->validated($request, true);
        $unit = $data['interval_unit'] ?? 'minutes';
        $value = (int) $data['interval_value'];
        $data['id'] = $data['id'] ?? (string) Str::uuid();
        $data['status'] = 'active';
        $data['is_active'] = true;
        $data['next_run_at'] = $data['next_run_at'] ?? $this->nextRun($value, $unit);
        $task = $project->tasks()->create($data);
        return response()->json(['data' => $task->loadCount('executions')], 201);
    }

    public function update(Request $request, ScrapingTask $task): JsonResponse
    {
        $data = $this->validated($request);
        if (isset($data['interval_value']) || isset($data['interval_unit'])) {
            $value = (int) ($data['interval_value'] ?? $task->interval_value);
            $unit = $data['interval_unit'] ?? $task->interval_unit;
            $data['next_run_at'] = $task->is_active ? $this->nextRun($value, $unit) : null;
        }
        if (array_key_exists('is_active', $data)) {
            $data['status'] = $data['is_active'] ? 'active' : 'paused';
            if (!$data['is_active']) $data['next_run_at'] = null;
        }
        $task->update($data);
        return response()->json(['data' => $task->fresh()->loadCount('executions')]);
    }

    public function pause(ScrapingTask $task): JsonResponse
    {
        $task->update(['status' => 'paused', 'is_active' => false, 'next_run_at' => null]);
        return response()->json(['data' => $task->fresh()]);
    }

    public function resume(ScrapingTask $task): JsonResponse
    {
        $task->update(['status' => 'active', 'is_active' => true, 'next_run_at' => $this->nextRun($task->interval_value, $task->interval_unit)]);
        return response()->json(['data' => $task->fresh()]);
    }

    public function schedule(Request $request, ScrapingTask $task): JsonResponse
    {
        $data = $request->validate(['last_run_at' => ['nullable', 'date'], 'next_run_at' => ['nullable', 'date']]);
        $task->update($data);
        return response()->json(['data' => $task->fresh()]);
    }

    public function destroy(ScrapingTask $task): JsonResponse
    {
        $task->update(['status' => 'disabled', 'is_active' => false, 'next_run_at' => null]);
        $task->delete();
        return response()->json(['message' => 'Tarea desactivada; las ejecuciones e historial se conservan.']);
    }

    private function validated(Request $request, bool $creating = false): array
    {
        return $request->validate([
            'id' => [$creating ? 'nullable' : 'sometimes', 'string', 'max:80'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'interval_value' => [$creating ? 'required' : 'sometimes', 'integer', 'min:1', 'max:525600'],
            'interval_unit' => ['sometimes', 'in:minutes,hours,days'],
            'is_active' => ['sometimes', 'boolean'],
            'next_run_at' => ['sometimes', 'nullable', 'date'],
            'schedule_config' => ['sometimes', 'nullable', 'array'],
        ]);
    }

    private function nextRun(int $value, string $unit): Carbon
    {
        return now()->add($unit, $value);
    }
}