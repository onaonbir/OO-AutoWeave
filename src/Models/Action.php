<?php

namespace OnaOnbir\OOAutoWeave\Models;

use Illuminate\Database\Eloquent\Model;
use OnaOnbir\OOAutoWeave\Models\Traits\JsonCast;

class Action extends Model
{
    public function getTable(): string
    {
        return config('oo-auto-weave.tables.actions');
    }

    protected $fillable = [
        'action_set_id',
        'type',
        'branch_type',
        'parameters',
        'settings',
        'order',
        'status',
    ];

    protected $casts = [
        'parameters' => JsonCast::class,
        'settings' => JsonCast::class,
    ];

    public function actionSet()
    {
        return $this->belongsTo(
            config('oo-auto-weave.models.action_set'),
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
