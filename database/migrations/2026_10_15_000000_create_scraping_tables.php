<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->string('id', 80)->primary();
            $table->string('name');
            $table->text('url');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('scraping_elements', function (Blueprint $table) {
            $table->string('id', 80)->primary();
            $table->string('project_id', 80);
            $table->string('name');
            $table->text('selector_css');
            $table->string('tag_name')->default('');
            $table->json('attributes')->nullable();
            $table->string('extraction_method', 40)->default('visible_text');
            $table->string('attribute_name')->nullable();
            $table->string('data_type', 40)->default('text');
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index(['project_id', 'is_active']);
        });

        Schema::create('scraping_tasks', function (Blueprint $table) {
            $table->string('id', 80)->primary();
            $table->string('project_id', 80);
            $table->string('name');
            $table->unsignedInteger('interval_value');
            $table->string('interval_unit', 16)->default('minutes');
            $table->string('status', 20)->default('active');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->json('schedule_config')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index(['project_id', 'status']);
        });

        Schema::create('executions', function (Blueprint $table) {
            $table->string('id', 80)->primary();
            $table->string('project_id', 80);
            $table->string('task_id', 80)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->default(0);
            $table->string('status', 20)->default('running');
            $table->unsignedInteger('fields_processed')->default(0);
            $table->unsignedInteger('fields_succeeded')->default(0);
            $table->unsignedInteger('fields_failed')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('task_id')->references('id')->on('scraping_tasks')->nullOnDelete();
            $table->index(['project_id', 'started_at']);
        });

        Schema::create('history_values', function (Blueprint $table) {
            $table->string('id', 80)->primary();
            $table->string('project_id', 80);
            $table->string('execution_id', 80);
            $table->string('element_id', 80)->nullable();
            $table->string('element_name');
            $table->string('data_type', 40)->default('text');
            $table->longText('raw_value')->nullable();
            $table->longText('normalized_value')->nullable();
            $table->decimal('numeric_value', 20, 6)->nullable();
            $table->string('currency', 12)->nullable();
            $table->string('status', 20)->default('valid');
            $table->timestamp('captured_at')->index();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('execution_id')->references('id')->on('executions')->cascadeOnDelete();
            $table->foreign('element_id')->references('id')->on('scraping_elements')->nullOnDelete();
            $table->index(['project_id', 'data_type', 'captured_at']);
            $table->index(['element_id', 'captured_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('history_values');
        Schema::dropIfExists('executions');
        Schema::dropIfExists('scraping_tasks');
        Schema::dropIfExists('scraping_elements');
        Schema::dropIfExists('projects');
    }
};