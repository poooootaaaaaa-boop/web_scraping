<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Project::query()->withCount(['elements', 'tasks'])->latest('updated_at')->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:80', 'unique:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:active,inactive'],
            'config' => ['nullable', 'array'],
        ]);
        $project = Project::create($data + ['id' => $data['id'] ?? (string) Str::uuid()]);
        return response()->json(['data' => $project->loadCount(['elements', 'tasks'])], 201);
    }

    public function show(Project $project): JsonResponse
    {
        return response()->json(['data' => $project->load(['elements', 'tasks'])->loadCount(['elements', 'tasks'])]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['sometimes', 'required', 'url:http,https', 'max:2048'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'in:active,inactive'],
            'config' => ['sometimes', 'nullable', 'array'],
        ]);
        $project->update($data);
        return response()->json(['data' => $project->fresh()->loadCount(['elements', 'tasks'])]);
    }

    public function destroy(Project $project): JsonResponse
    {
        DB::transaction(function () use ($project) {
            $project->tasks()->update(['status' => 'disabled', 'is_active' => false, 'next_run_at' => null]);
            $project->delete();
        });
        return response()->json(['message' => 'Proyecto archivado; su historial se conserva.']);
    }
}