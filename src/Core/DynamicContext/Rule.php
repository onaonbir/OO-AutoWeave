<?php

namespace OnaOnbir\OOAutoWeave\Core\DynamicContext;

class Rule
{
    protected array $rules = [];

    public static function make(): static
    {
        return new static;
    }

    public function and(string $key, string $operator, mixed $value): static
    {
        $this->rules[] = [
            'key' => $key,
            'operator' => $operator,
            'value' => $value,
            'type' => 'and',
        ];

        return $this;
    }

    public function or(string $key, string $operator, mixed $value): static
    {
        $this->rules[] = [
            'key' => $key,
            'operator' => $operator,
            'value' => $value,
            'type' => 'or',
        ];

        return $this;
    }

    public function get(): array
    {
        return $this->rules;
    }

    public function evaluateAgainst(mixed $model, array $filterableColumns = []): bool
    {
        $context = is_array($model)
            ? $model
            : ModelExtractor::extract($model, $filterableColumns);

        return RuleMatcher::matches($this->get(), $context);
    }
}
