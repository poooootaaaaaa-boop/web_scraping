<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Models\Project;
use App\Models\ScrapingElement;
use App\Models\ScrapingTask;
use Tests\TestCase;

class ApiPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_fields_scheduled_task_and_history_are_persisted(): void
    {
        $project = $this->postJson('/api/projects', [
            'id' => 'project-test', 'name' => 'Test product', 'url' => 'https://example.com/product',
        ])->assertCreated()->json('data');

        $field = $this->postJson('/api/projects/project-test/fields', [
            'id' => 'field-test', 'name' => 'Price', 'selector_css' => '.price',
            'extraction_method' => 'visible_text', 'data_type' => 'price', 'is_active' => true,
        ])->assertCreated()->json('data');
        $this->putJson('/api/fields/field-test', ['name' => 'Retail price', 'selector_css' => '.sale-price'])
            ->assertOk()->assertJsonPath('data.name', 'Retail price');

        $this->postJson('/api/projects/project-test/tasks', [
            'id' => 'task-test', 'name' => 'Custom interval', 'interval_value' => 210, 'interval_unit' => 'minutes',
        ])->assertCreated()->assertJsonPath('data.interval_value', 210);
        $this->patchJson('/api/tasks/task-test/pause')->assertOk()->assertJsonPath('data.status', 'paused');
        $this->patchJson('/api/tasks/task-test/resume')->assertOk()->assertJsonPath('data.status', 'active');

        $execution = $this->postJson('/api/executions', [
            'id' => 'execution-test', 'task_id' => 'task-test', 'started_at' => now()->toISOString(),
        ])->assertCreated()->json('data');
        $this->postJson('/api/executions/execution-test/values', ['values' => [[
            'id' => 'value-test', 'element_id' => 'field-test', 'captured_at' => now()->toISOString(),
            'raw_value' => '$19.99 USD', 'numeric_value' => 19.99, 'currency' => 'USD', 'status' => 'valid',
        ]]])->assertCreated();
        $this->patchJson('/api/executions/execution-test/complete', [
            'finished_at' => now()->toISOString(), 'duration_ms' => 123,
            'status' => 'success', 'fields_succeeded' => 1, 'fields_failed' => 0,
        ])->assertOk();

        $this->deleteJson('/api/fields/field-test')->assertOk();
        $this->getJson('/api/projects/project-test/history')->assertOk()
            ->assertJsonPath('data.0.element_name', 'Retail price')
            ->assertJsonPath('data.0.data_type', 'price');
        $this->getJson('/api/fields/field-test/prices')->assertOk()
            ->assertJsonPath('data.0.price', 19.99);
        $this->getJson('/api/projects/project-test/prices/statistics')->assertOk()
            ->assertJsonPath('data.currency', 'USD');

        $this->deleteJson('/api/projects/project-test')->assertOk();
        $this->assertDatabaseHas('history_values', ['id' => 'value-test', 'raw_value' => '$19.99 USD']);
        $this->assertDatabaseHas('projects', ['id' => $project['id']]);
        $this->getJson('/api/projects/project-test/history')->assertOk()
            ->assertJsonPath('data.0.raw_value', '$19.99 USD');
    }

    public function test_server_scheduler_extracts_css_and_does_not_repeat_a_task(): void
    {
        Http::fake(['https://1.1.1.1/*' => Http::response('<html><span class="price">$19.99 USD</span></html>')]);
        $project = Project::create(['id' => 'scheduled-project', 'name' => 'Scheduled', 'url' => 'https://1.1.1.1/item']);
        ScrapingElement::create([
            'id' => 'scheduled-price', 'project_id' => $project->id, 'name' => 'Price',
            'selector_css' => '.price', 'data_type' => 'price', 'is_active' => true,
        ]);
        ScrapingTask::create([
            'id' => 'scheduled-task', 'project_id' => $project->id, 'name' => 'Due task',
            'interval_value' => 210, 'interval_unit' => 'minutes', 'status' => 'active',
            'is_active' => true, 'next_run_at' => now()->subMinute(),
        ]);

        $this->artisan('scraping:run-due')->assertSuccessful();
        $this->artisan('scraping:run-due')->assertSuccessful();

        $this->assertDatabaseCount('executions', 1);
        $this->assertDatabaseHas('history_values', [
            'element_name' => 'Price', 'raw_value' => '$19.99 USD', 'numeric_value' => 19.99,
            'currency' => 'USD', 'status' => 'valid',
        ]);
        Http::assertSentCount(1);
    }

    public function test_legacy_backup_import_is_idempotent_and_exportable(): void
    {
        $payload = ['items' => [[
            'id' => 'legacy-project', 'name' => 'Imported project', 'url' => 'https://example.com/legacy',
        ]]];
        $this->postJson('/api/data/import/projects', $payload)->assertOk();
        $this->postJson('/api/data/import/projects', $payload)->assertOk();

        $this->assertDatabaseCount('projects', 1);
        $this->getJson('/api/data/export')->assertOk()
            ->assertJsonPath('data.projects.0.id', 'legacy-project');
    }
}