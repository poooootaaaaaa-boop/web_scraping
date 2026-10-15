<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HistoryValue;
use App\Models\Project;
use App\Models\ScrapingElement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $query = $project->historyValues()->with('execution')->orderByDesc('captured_at');
        if ($request->filled('since')) $query->where('captured_at', '>=', $request->query('since'));
        return response()->json($query->paginate(min(max((int) $request->query('limit', 200), 1), 500)));
    }

    public function byExecution(Project $project, string $executionId): JsonResponse
    {
        return response()->json(['data' => $project->historyValues()->where('execution_id', $executionId)->orderBy('captured_at')->get()]);
    }

    public function prices(Project $project): JsonResponse
    {
        return response()->json(['data' => $this->validPrices($project->historyValues())->get()]);
    }

    public function fieldPrices(Request $request, string $field): JsonResponse
    {
        $fieldModel = ScrapingElement::withTrashed()->findOrFail($field);
        $query = $this->validPrices(HistoryValue::query()->where('element_id', $fieldModel->id));
        if ($request->filled('since')) $query->where('captured_at', '>=', $request->query('since'));
        return response()->json(['data' => $query->get()->map(fn ($row) => [
            'timestamp' => $row->captured_at->valueOf(),
            'captured_at' => $row->captured_at->toISOString(),
            'price' => (float) $row->numeric_value,
            'currency' => $row->currency,
            'raw_value' => $row->raw_value,
            'status' => $row->status,
        ])]);
    }

    public function statistics(Request $request, Project $project): JsonResponse
    {
        $query = $this->validPrices($project->historyValues());
        if ($request->filled('since')) $query->where('captured_at', '>=', $request->query('since'));
        return response()->json(['data' => $this->metrics($query)]);
    }

    public function fieldStatistics(Request $request, string $field): JsonResponse
    {
        $fieldModel = ScrapingElement::withTrashed()->findOrFail($field);
        $query = $this->validPrices(HistoryValue::query()->where('element_id', $fieldModel->id));
        if ($request->filled('since')) $query->where('captured_at', '>=', $request->query('since'));
        return response()->json(['data' => $this->metrics($query)]);
    }

    private function validPrices($query)
    {
        $currency = (clone $query)->whereNotNull('currency')->orderByDesc('captured_at')->value('currency');
        return $query->where('data_type', 'price')->where('status', 'valid')->whereNotNull('numeric_value')
            ->when($currency, fn ($builder) => $builder->where('currency', $currency), fn ($builder) => $builder->whereNull('currency'))
            ->orderBy('captured_at');
    }

    private function metrics($query): array
    {
        $stats = (clone $query)->reorder()->selectRaw('COUNT(*) as count, MIN(numeric_value) as min, MAX(numeric_value) as max, AVG(numeric_value) as avg, MAX(captured_at) as last_captured_at')->first();
        $latest = (clone $query)->reorder()->latest('captured_at')->first();
        return [
            'current' => $latest ? (float) $latest->numeric_value : null,
            'min' => $stats?->min === null ? null : (float) $stats->min,
            'max' => $stats?->max === null ? null : (float) $stats->max,
            'avg' => $stats?->avg === null ? null : round((float) $stats->avg, 2),
            'currency' => $latest?->currency,
            'count' => (int) ($stats?->count ?? 0),
            'lastCapturedAt' => $stats?->last_captured_at,
        ];
    }
}