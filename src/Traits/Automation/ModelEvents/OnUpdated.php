<?php

namespace OnaOnbir\OOAutoWeave\Traits\Automation\ModelEvents;

use Illuminate\Database\Eloquent\Model;
use OnaOnbir\OOAutoWeave\Jobs\DispatchTriggerForUpdatedModel;

trait OnUpdated
{
    public static function bootOnUpdated(): void
    {
        static::updated(function (Model $model) {
            DispatchTriggerForUpdatedModel::dispatch(
                get_class($model),
                $model->getKey(),
                $model->getChanges(),
                $model->getOriginal()
            );
        });
    }
}
