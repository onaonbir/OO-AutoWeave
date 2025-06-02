<?php

namespace OnaOnbir\OOAutoWeave\Core\DTO;

use Illuminate\Database\Eloquent\Model;

final class TriggerHandlerResult
{
    public function __construct(
        public bool $shouldExecute = true,
        public ?Model $trigger = null,
        public array $context = [],
    ) {}

    public static function allow(Model $trigger, array $context = []): static
    {
        return new self(true, $trigger, $context);
    }

    public static function deny(): static
    {
        return new static(false);
    }
}
