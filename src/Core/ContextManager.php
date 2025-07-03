<?php

namespace OnaOnbir\OOAutoWeave\Core;

use Illuminate\Support\Arr;
use OnaOnbir\OOAutoWeave\Models\FlowRun;

class ContextManager
{
    protected static ?self $instance = null;

    public static function bindToRun(FlowRun $run): self
    {
        return self::$instance = new self($run);
    }

    public static function instance(): ?self
    {
        return self::$instance;
    }

    protected FlowRun $flowRun;

    public array $contextStack = [];

    public function __construct(FlowRun $flowRun)
    {
        $this->flowRun = $flowRun;
        $this->loadContext();
    }

    protected function loadContext(): void
    {
        $context = $this->flowRun->context ?? [];

        $this->contextStack['global'] = $context['__global'] ?? [];
        $this->contextStack['shared'] = $context['__shared'] ?? [];
        $this->contextStack['nodes'] = $context['nodes'] ?? [];
        $this->contextStack['temp'] = [];
        $this->contextStack['system'] = $context['__system'] ?? [
            'flow_run' => [
                'id' => $this->flowRun->getKey(),
                'class' => get_class($this->flowRun),
            ],
        ];
    }

    public function start(string $scope = 'global', ?string $nodeKey = null, array $data = []): self
    {
        $this->setBatch($data, $scope, $nodeKey);
        $this->persist();

        return $this;
    }

    public function get(string $key, $default = null, ?string $nodeKey = null, ?string $scope = null)
    {
        if ($scope) {
            return $this->getFromScope($key, $scope, $nodeKey, $default);
        }

        foreach (['temp', 'nodes', 'shared', 'global'] as $currentScope) {
            $value = $this->getFromScope($key, $currentScope, $nodeKey);
            if ($value !== null) {
                return $value;
            }
        }

        return $default;
    }

    protected function getFromScope(string $key, string $scope, ?string $nodeKey = null, $default = null)
    {
        return match ($scope) {
            'global' => Arr::get($this->contextStack['global'], $key, $default),
            'shared' => Arr::get($this->contextStack['shared'], $key, $default),
            'nodes' => $nodeKey ? Arr::get($this->contextStack['nodes'][$nodeKey] ?? [], $key, $default) : $default,
            'temp' => Arr::get($this->contextStack['temp'], $key, $default),
            default => $default,
        };
    }

    public function set(string $key, $value, string $scope = 'global', ?string $nodeKey = null): self
    {
        if ($scope === 'global') {
            Arr::set($this->contextStack['global'], $key, $value);
        } elseif ($scope === 'shared') {
            Arr::set($this->contextStack['shared'], $key, $value);
        } elseif ($scope === 'nodes') {
            if (! $nodeKey) {
                throw new \InvalidArgumentException('Node key is required for nodes scope');
            }
            $this->contextStack['nodes'][$nodeKey] ??= [];
            Arr::set($this->contextStack['nodes'][$nodeKey], $key, $value);
        } elseif ($scope === 'temp') {
            Arr::set($this->contextStack['temp'], $key, $value);
        }

        return $this;
    }

    public function setBatch(array $data, string $scope = 'global', ?string $nodeKey = null): self
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value, $scope, $nodeKey);
        }

        return $this;
    }

    public function mergeBatch(array $data, string $scope = 'global', ?string $nodeKey = null): self
    {
        foreach ($data as $key => $value) {
            $existing = $this->get($key, null, $nodeKey, $scope);
            if (is_array($existing) && is_array($value)) {
                $value = array_merge($existing, $value);
            }
            $this->set($key, $value, $scope, $nodeKey);
        }

        return $this;
    }

    public function forget(string $key, string $scope = 'global', ?string $nodeKey = null): self
    {
        if ($scope === 'global') {
            Arr::forget($this->contextStack['global'], $key);
        } elseif ($scope === 'shared') {
            Arr::forget($this->contextStack['shared'], $key);
        } elseif ($scope === 'temp') {
            Arr::forget($this->contextStack['temp'], $key);
        } elseif ($scope === 'nodes' && $nodeKey && isset($this->contextStack['nodes'][$nodeKey])) {
            Arr::forget($this->contextStack['nodes'][$nodeKey], $key);
        }

        return $this;
    }

    public function all(): array
    {
        return $this->contextStack;
    }

    public function getScope(string $scope, ?string $nodeKey = null): array
    {
        return match ($scope) {
            'global' => $this->contextStack['global'],
            'shared' => $this->contextStack['shared'],
            'nodes' => $nodeKey ? ($this->contextStack['nodes'][$nodeKey] ?? []) : ($this->contextStack['nodes'] ?? []),
            'temp' => $this->contextStack['temp'],
            default => [],
        };
    }

    public function getNodeContext(string $nodeKey): array
    {
        return $this->contextStack['nodes'][$nodeKey] ?? [];
    }

    public function flushTemp(): self
    {
        $this->contextStack['temp'] = [];

        return $this;
    }

    public function persist(): void
    {
        $context = [
            '__global' => $this->contextStack['global'],
            '__shared' => $this->contextStack['shared'],
            '__nodes' => $this->contextStack['nodes'] ?? [],
            '__system' => $this->contextStack['system'] ?? [],
            '__temporary' => $this->contextStack['temp'] ?? [],
        ];

        $this->flowRun->update(['context' => $context]);
        $this->flowRun->fresh();
    }

    public function dump(?string $scope = null): array
    {
        return $scope
            ? $this->getScope($scope)
            : $this->contextStack;
    }

    public function getMetrics(): array
    {
        return [
            'total_keys' => $this->countAllKeys(),
            'scope_breakdown' => [
                'global' => count($this->contextStack['global']),
                'shared' => count($this->contextStack['shared']),
                'nodes' => count($this->contextStack['nodes'] ?? []),
                'temporary' => count($this->contextStack['temp']),
            ],
            'memory_usage' => strlen(json_encode($this->contextStack)),
        ];
    }

    protected function countAllKeys(): int
    {
        return count($this->contextStack['global'])
            + count($this->contextStack['shared'])
            + count($this->contextStack['temp'])
            + collect($this->contextStack['nodes'] ?? [])->sum(fn ($ctx) => count($ctx));
    }

    // TODO MUST BE CHANGE ??

    public function resolveArrayWithTemplates(array|string|null $input): array
    {
        $items = is_array($input) ? $input : (is_string($input) ? [$input] : []);
        $resolved = [];

        foreach ($items as $item) {
            if (is_string($item) && preg_match('/\{\{(.+?)\}\}/', $item, $matches)) {
                $dotPath = trim($matches[1]);

                // scope otomatik çıkarılabiliyor: global.model.user.id → ['global', 'model.user.id']
                [$scope, $key] = str_contains($dotPath, '.')
                    ? explode('.', $dotPath, 2)
                    : ['global', $dotPath];

                $value = $this->get($key, null, null, $scope);

                if (is_array($value)) {
                    $resolved = array_merge($resolved, $value);
                } elseif (! is_null($value)) {
                    $resolved[] = $value;
                }

                // Bulunamayanlar otomatik atlanıyor
            } else {
                $resolved[] = $item;
            }
        }

        return array_values(array_filter($resolved));
    }

    public function resolveValueFromTemplate(string|int|null $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (preg_match('/\{\{(.+?)\}\}/', $value, $matches)) {
            $dotPath = trim($matches[1]);

            [$scope, $key] = str_contains($dotPath, '.')
                ? explode('.', $dotPath, 2)
                : ['global', $dotPath];

            return $this->get($key, null, null, $scope);
        }

        return $value;
    }
}
