<?php

namespace OnaOnbir\OOAutoWeave\Models;

use Illuminate\Database\Eloquent\Model;
use OnaOnbir\OOAutoWeave\Models\Traits\JsonCast;

class ActionSet extends Model
{
    public function getTable(): string
    {
        return config('oo-auto-weave.tables.action_sets');
    }

    protected $fillable = [
        'trigger_id',
        'execution_type',
        'rules',
        'settings',
        'order',
        'status',
    ];

    protected $casts = [
        'rules' => JsonCast::class,
        'settings' => JsonCast::class,
    ];

    public function trigger()
    {
        return $this->belongsTo(
            config('oo-auto-weave.models.trigger'),
            'trigger_id'
        );
    }

    public function actions()
    {
        return $this->hasMany(
            config('oo-auto-weave.models.action'),
            'action_set_id'
        );
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
