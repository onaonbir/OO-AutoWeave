<?php

namespace OnaOnbir\OOAutoWeave\Models\Traits;

trait HasAutoOrder
{
    protected static function bootHasAutoOrder()
    {
        static::creating(function ($model) {
            if (empty($model->order)) {
                $query = static::query();

                if (isset($model->autoOrderScopeColumn) && isset($model->{$model->autoOrderScopeColumn})) {
                    $query->where($model->autoOrderScopeColumn, $model->{$model->autoOrderScopeColumn});
                }

                $lastOrder = $query->max('order');

                $model->order = $lastOrder ? $lastOrder + 1 : 1;
            }
        });
    }
}
