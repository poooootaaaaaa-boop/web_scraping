<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Project extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'name', 'url', 'description', 'status', 'config'];
    protected function casts(): array { return ['config' => 'array']; }
    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        return parent::resolveRouteBindingQuery($query->withTrashed(), $value, $field);
    }
    public function elements(): HasMany { return $this->hasMany(ScrapingElement::class, 'project_id'); }
    public function tasks(): HasMany { return $this->hasMany(ScrapingTask::class, 'project_id'); }
    public function executions(): HasMany { return $this->hasMany(Execution::class); }
    public function historyValues(): HasMany { return $this->hasMany(HistoryValue::class); }
}