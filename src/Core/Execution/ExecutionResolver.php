<?php

namespace OnaOnbir\OOAutoWeave\Core\Execution;

use OnaOnbir\OOAutoWeave\Core\Data\ContextExtractor;
use OnaOnbir\OOAutoWeave\Core\Data\DynamicReplacer;
use OnaOnbir\OOAutoWeave\Core\Data\RuleMatcher;
use OnaOnbir\OOAutoWeave\Core\Registry\ActionRegistry;
use OnaOnbir\OOAutoWeave\Core\Support\Logger;
use OnaOnbir\OOAutoWeave\Enums\ExecutionTypeEnum;
use OnaOnbir\OOAutoWeave\Models\Action;
use OnaOnbir\OOAutoWeave\Models\ActionSet;
use OnaOnbir\OOAutoWeave\Models\Trigger;

class ExecutionResolver
{
    public static function runTrigger(Trigger $trigger, array $context = []): void
    {
        $source = 'ExecutionResolver::runTrigger';

        Logger::info('Running trigger execution', [
            'trigger_id' => $trigger->id,
            'trigger_key' => $trigger->key,
        ], $source);

        foreach ($trigger->actionSets()->active()->ordered()->get() as $set) {
            self::runActionSet($set, array_merge($context, [
                'trigger' => $trigger,
            ]));
        }
    }

    public static function runActionSet(ActionSet $set, array $context = []): void
    {
        $source = 'ExecutionResolver::runActionSet';

        Logger::info('Running action set', [
            'action_set_id' => $set->id,
            'execution_type' => $set->execution_type,
            'trigger_id' => $set->trigger_id,
        ], $source);

        $model = $context['model'] ?? null;
        $attributes = $context['attributes'] ?? [];

        $contextData = $model
            ? ContextExtractor::extract($model, method_exists($model, 'filterableColumns') ? $model::filterableColumns(1) : [])
            : $attributes;

        if ($set->execution_type === ExecutionTypeEnum::RULED->value) {
            $isMatched = RuleMatcher::matches($set->rules, $contextData);
            $branch = $isMatched ? 'true_branch' : 'false_branch';

            Logger::info('Rule evaluated for action set', [
                'result' => $isMatched,
                'branch' => $branch,
            ], $source);

            $actions = $set->actions()->where('branch_type', $branch)->ordered()->get();
        } else {
            $actions = $set->actions()->where('branch_type', 'default')->ordered()->get();
        }

        foreach ($actions as $action) {
            self::runAction($action, $action->parameters, $contextData);
        }
    }

    public static function runAction(Action $action, array $parameters = [], array $context = []): void
    {
        $source = 'ExecutionResolver::runAction';

        Logger::info('Executing action', [
            'action_id' => $action->id,
            'type' => $action->type,
            'branch_type' => $action->branch_type,
        ], $source);

        try {
            $finalParams = DynamicReplacer::replace($parameters, $context);

            Logger::info('Final action parameters after replacement', [
                'parameters' => $finalParams,
            ], $source);

            ActionRegistry::execute(
                $action->type,
                $finalParams,
                $context
            );

            Logger::info('Action executed successfully', [
                'action_id' => $action->id,
            ], $source);
        } catch (\Throwable $e) {
            Logger::error('Action execution failed', [
                'action_id' => $action->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $source);
        }
    }
}
