<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Execution extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'project_id', 'task_id', 'scheduled_at', 'started_at', 'finished_at', 'duration_ms', 'status', 'fields_processed', 'fields_succeeded', 'fields_failed', 'error_message'];
    protected function casts(): array { return ['scheduled_at' => 'immutable_datetime', 'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime', 'duration_ms' => 'integer']; }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function task(): BelongsTo { return $this->belongsTo(ScrapingTask::class, 'task_id'); }
    public function historyValues(): HasMany { return $this->hasMany(HistoryValue::class); }
}