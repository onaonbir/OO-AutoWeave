<?php

namespace OnaOnbir\OOAutoWeave\Models;

use Illuminate\Database\Eloquent\Model;
use OnaOnbir\OOAutoWeave\Models\Traits\CodeGenerator;
use OnaOnbir\OOAutoWeave\Models\Traits\JsonCast;
use OnaOnbir\OOSettings\Traits\HasSettings;

class Automation extends Model
{
    use CodeGenerator, HasSettings;

    public function getTable(): string
    {
        return config('oo-auto-weave.tables.automations');
    }

    protected $fillable = [
        'code', 'name', 'description', 'attributes', 'settings', 'status',
    ];

    protected $casts = [
        'attributes' => JsonCast::class,
        'settings' => JsonCast::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->code ??= self::createUniqueTextKey($model, 'code', 20, 'AUTO_', true);
        });
    }

    public function triggers()
    {
        return $this->hasMany(
            config('oo-auto-weave.models.trigger'),
            'automation_id'
        );
    }
}
