<?php

namespace OnaOnbir\OOAutoWeave\Traits\Automation\ModelEvents;

use Illuminate\Database\Eloquent\Model;
use OnaOnbir\OOAutoWeave\Jobs\DispatchTriggerForCreatedModel;

trait OnCreated
{
    public static function bootOnCreated(): void
    {
        static::created(function (Model $model) {
            DispatchTriggerForCreatedModel::dispatch(
                get_class($model),
                $model->getKey(),
                $model->getAttributes()
            );
        });
    }
}
