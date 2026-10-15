<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ScrapingElement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FieldController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        return response()->json(['data' => $project->elements()->orderBy('created_at')->get()]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $this->validated($request, true);
        $field = $project->elements()->create($data + ['id' => $data['id'] ?? (string) Str::uuid()]);
        return response()->json(['data' => $field], 201);
    }

    public function show(ScrapingElement $field): JsonResponse
    {
        return response()->json(['data' => $field]);
    }

    public function update(Request $request, ScrapingElement $field): JsonResponse
    {
        $field->update($this->validated($request));
        return response()->json(['data' => $field->fresh()]);
    }

    public function destroy(ScrapingElement $field): JsonResponse
    {
        $field->update(['is_active' => false]);
        $field->delete();
        return response()->json(['message' => 'Campo archivado; las capturas anteriores se conservan.']);
    }

    private function validated(Request $request, bool $creating = false): array
    {
        return $request->validate([
            'id' => [$creating ? 'nullable' : 'sometimes', 'string', 'max:80'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'selector_css' => [$creating ? 'required' : 'sometimes', 'string', 'max:2048'],
            'tag_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'attributes' => ['sometimes', 'nullable', 'array'],
            'extraction_method' => ['sometimes', 'string', 'max:40'],
            'attribute_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'data_type' => ['sometimes', 'string', 'max:40'],
            'config' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}