<?php

namespace App\Console\Commands;

use App\Models\ScrapingTask;
use App\Services\ScraperRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RunDueTasks extends Command
{
    protected $signature = 'scraping:run-due';
    protected $description = 'Run due active scraping tasks once';

    public function handle(ScraperRunner $runner): int
    {
        $tasks = ScrapingTask::query()->where('is_active', true)->where('status', 'active')
            ->whereNotNull('next_run_at')->where('next_run_at', '<=', now())->with('project')->orderBy('next_run_at')->get();
        foreach ($tasks as $task) {
            $lock = Cache::lock('scraping-task:'.$task->id, 120);
            if (!$lock->get()) continue;
            try {
                $runner->run($task, optional($task->next_run_at)->toISOString());
                $this->info("Completed task {$task->id}");
            } catch (Throwable $exception) {
                report($exception);
                $this->error("Task {$task->id}: {$exception->getMessage()}");
            } finally {
                $lock->release();
            }
        }
        return self::SUCCESS;
    }
}