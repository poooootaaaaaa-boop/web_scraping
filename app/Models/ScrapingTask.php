<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrapingTask extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'scraping_tasks';
    protected $fillable = ['id', 'project_id', 'name', 'interval_value', 'interval_unit', 'status', 'is_active', 'last_run_at', 'next_run_at', 'schedule_config'];
    protected function casts(): array { return ['interval_value' => 'integer', 'is_active' => 'boolean', 'last_run_at' => 'immutable_datetime', 'next_run_at' => 'immutable_datetime', 'schedule_config' => 'array']; }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function executions(): HasMany { return $this->hasMany(Execution::class, 'task_id'); }
}