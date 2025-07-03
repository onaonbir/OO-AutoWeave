<?php

namespace OnaOnbir\OOAutoWeave\Models;

use Illuminate\Database\Eloquent\Model;
use OnaOnbir\OOAutoWeave\Models\Traits\HasFlowStructure;
use OnaOnbir\OOAutoWeave\Models\Traits\JsonCast;

class Flow extends Model
{
    use HasFlowStructure;

    public function getTable(): string
    {
        return config('oo-auto-weave.tables.flows');
    }

    protected $fillable = [
        'key',
        'name',
        'structure',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'structure' => JsonCast::class,
        'metadata' => JsonCast::class,
        'is_active' => 'boolean',
    ];
}
