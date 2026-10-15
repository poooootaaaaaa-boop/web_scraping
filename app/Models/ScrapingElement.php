<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrapingElement extends Model
{
    use SoftDeletes;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'scraping_elements';
    protected $fillable = ['id', 'project_id', 'name', 'selector_css', 'tag_name', 'attributes', 'extraction_method', 'attribute_name', 'data_type', 'config', 'is_active'];
    protected function casts(): array { return ['attributes' => 'array', 'config' => 'array', 'is_active' => 'boolean']; }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function historyValues(): HasMany { return $this->hasMany(HistoryValue::class, 'element_id'); }
}