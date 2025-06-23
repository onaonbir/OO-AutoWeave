<?php

namespace OnaOnbir\OOAutoWeave\Models;

use Illuminate\Database\Eloquent\Model;
use OnaOnbir\OOAutoWeave\Models\Traits\JsonCast;

class Trigger extends Model
{
    public function getTable(): string
    {
        return config('oo-auto-weave.tables.triggers');
    }

    protected $fillable = [
        'automation_id',
        'morphable_type', // BU ALAN YENİ
        'morphable_id', // BU ALAN YENİ
        'key',
        'group',
        'type',
        'label',
        'settings',
        'order',
        'status',
    ];

    protected $casts = [
        'settings' => JsonCast::class,
    ];

    public function automation()
    {
        return $this->belongsTo(
            config('oo-auto-weave.models.automation'),
            'automation_id'
        );
    }

    public function actionSets()
    {
        return $this->hasMany(
            config('oo-auto-weave.models.action_set'),
            'trigger_id'
        );
    }

    public function morphable()
    {
        return $this->morphTo();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeOnlyMorphable($query)
    {
        return $query->whereNotNull('morphable_type')->whereNotNull('morphable_id');
    }
}
