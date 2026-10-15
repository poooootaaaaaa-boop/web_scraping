<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoryValue extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'project_id', 'execution_id', 'element_id', 'element_name', 'data_type', 'raw_value', 'normalized_value', 'numeric_value', 'currency', 'status', 'captured_at', 'error_message'];
    protected function casts(): array { return ['numeric_value' => 'decimal:6', 'captured_at' => 'immutable_datetime']; }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function execution(): BelongsTo { return $this->belongsTo(Execution::class); }
    public function element(): BelongsTo { return $this->belongsTo(ScrapingElement::class, 'element_id')->withTrashed(); }
}