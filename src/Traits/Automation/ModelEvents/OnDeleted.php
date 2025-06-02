<?php

namespace OnaOnbir\OOAutoWeave\Traits\Automation\ModelEvents;

use Illuminate\Database\Eloquent\Model;
use OnaOnbir\OOAutoWeave\Jobs\DispatchTriggerForDeletedModel;

trait OnDeleted
{
    public static function bootOnDeleted(): void
    {
        static::deleting(function (Model $model) {
            DispatchTriggerForDeletedModel::dispatch(
                get_class($model),
                $model->getKey(),
                $model->getAttributes()
            );
        });
    }
}
