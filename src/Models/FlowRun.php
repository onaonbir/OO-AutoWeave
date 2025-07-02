<?php

namespace OnaOnbir\OOAutoWeave\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use OnaOnbir\OOAutoWeave\Enums\NodeStatus;
use OnaOnbir\OOAutoWeave\Models\Traits\JsonCast;

class FlowRun extends Model
{
    public function getTable(): string
    {
        return config('oo-auto-weave.tables.flow_runs');
    }

    protected $fillable = [
        'morphable_type',
        'morphable_id',
        'name',
        'base_structure',
        'node_states',
        'context',
        'status',
        'metadata',
    ];

    protected $casts = [
        'base_structure' => JsonCast::class,
        'node_states' => JsonCast::class,
        'context' => JsonCast::class,
        'metadata' => JsonCast::class,
    ];

    /**
     * İlişkili model (polymorphic)
     */
    public function morphable()
    {
        return $this->morphTo();
    }

    /**
     * Akıştaki tüm node'ları döner
     */
    public function getNodes(): Collection
    {
        return collect($this->base_structure['nodes'] ?? []);
    }

    /**
     * Akıştaki tüm bağlantıları (edge'leri) döner
     */
    public function getEdges(): Collection
    {
        return collect($this->base_structure['edges'] ?? []);
    }

    /**
     * Belirtilen key'e sahip node'u döner
     */
    public function getNode(string $key): ?array
    {
        return $this->getNodes()->firstWhere('key', $key);
    }

    /**
     * Node'un mevcut durumunu döner
     */
    public function getNodeState(string $key): ?array
    {
        return $this->node_states[$key] ?? null;
    }

    /**
     * Node durumlarını güncellenme tarihine göre sıralı şekilde döner
     */
    public function getOrderedNodeStates(): Collection
    {
        return collect($this->node_states ?? [])
            ->sortBy(fn ($state) => $state['started_at'] ?? now()->toIso8601String());
    }

    /**
     * Başlangıç node'larını döner (gelen bağlantısı olmayan node'lar)
     */
    public function getStartNodes(): Collection
    {
        $nodes = $this->getNodes();
        $edges = $this->getEdges();

        $nodesWithIncoming = $edges->pluck('connection.to')->unique()->all();

        return $nodes->filter(fn ($node) => ! in_array($node['key'], $nodesWithIncoming));
    }

    /**
     * İşlenmeye hazır node'ları döner (tüm öncel node'ları tamamlanmış olanlar)
     */
    public function getReadyNodes(): Collection
    {
        $processedNodes = collect($this->node_states ?? [])
            ->filter(fn ($state) => in_array($state['status'], [
                NodeStatus::Completed->value,
                NodeStatus::Skipped->value,
            ]))
            ->keys();

        $edges = $this->getEdges();

        return $this->getNodes()->filter(function ($node) use ($processedNodes, $edges) {
            // Başlangıç node'ları için özel kontrol
            if ($this->isStartNode($node['key'])) {
                return ! isset($this->node_states[$node['key']]);
            }

            // Gelen bağlantıları kontrol et
            $incomingNodes = $edges->where('connection.to', $node['key'])
                ->pluck('connection.from')
                ->all();

            return ! empty($incomingNodes) &&
                collect($incomingNodes)->every(fn ($k) => $processedNodes->contains($k));
        });
    }

    /**
     * Node'un başlangıç node'u olup olmadığını kontrol eder
     */
    public function isStartNode(string $nodeKey): bool
    {
        $edges = $this->getEdges();

        return $edges->where('connection.to', $nodeKey)->isEmpty();
    }

    /**
     * Akışın genel durumunu hesaplar
     */
    public function calculateStatus(): string
    {
        $nodes = $this->getNodes();
        $states = $this->node_states ?? [];

        if ($nodes->isEmpty()) {
            return 'completed';
        }

        $hasFailed = false;
        $allCompleted = true;

        foreach ($nodes as $node) {
            $nodeKey = $node['key'];
            $status = $states[$nodeKey]['status'] ?? 'queued';

            if ($status === NodeStatus::Failed->value) {
                $hasFailed = true;
                $allCompleted = false;
                break;
            }

            if (! in_array($status, [NodeStatus::Completed->value, NodeStatus::Skipped->value])) {
                $allCompleted = false;
            }
        }

        if ($hasFailed) {
            return 'error';
        }

        return $allCompleted ? 'completed' : 'running';
    }

    /**
     * Çalıştırma geçmişini döner (hangi node ne zaman çalıştı, sonuç neydi)
     */
    public function getExecutionPath(): array
    {
        return collect($this->node_states ?? [])
            ->map(function ($state, $key) {
                return [
                    'node_key' => $key,
                    'status' => $state['status'],
                    'started_at' => $state['started_at'] ?? null,
                    'finished_at' => $state['finished_at'] ?? null,
                    'result' => $state['node']['run_result'] ?? [],
                    'snapshot' => $state['node']['snapshot'] ?? [],
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Node durumlarının dağılımını döner
     */
    public function getStatusBreakdown(): array
    {
        $breakdown = [];

        foreach ($this->getNodes() as $node) {
            $status = $this->node_states[$node['key']]['status'] ?? NodeStatus::Queued->value;
            $breakdown[$status] = ($breakdown[$status] ?? 0) + 1;
        }

        return $breakdown;
    }

    /**
     * İlerleme durumunu döner (yüzde kaç tamamlandı gibi)
     */
    public function getProgress(): array
    {
        $total = $this->getNodes()->count();

        if ($total === 0) {
            return [
                'percentage' => 100,
                'completed' => 0,
                'total' => 0,
                'status_breakdown' => [],
            ];
        }

        $breakdown = $this->getStatusBreakdown();
        $completed = ($breakdown[NodeStatus::Completed->value] ?? 0) +
            ($breakdown[NodeStatus::Skipped->value] ?? 0);

        return [
            'percentage' => round(($completed / $total) * 100, 2),
            'completed' => $completed,
            'total' => $total,
            'status_breakdown' => $breakdown,
        ];
    }

    // FlowRun modeline bu metodu ekleyin
    public static function matchCondition(array $condition, array $context): bool
    {
        $conditionType = $condition['type'] ?? null;
        $key = $condition['key'] ?? null;
        $value = $condition['value'] ?? null;

        Log::info('context_here', ['val' => $context]);

        $contextValue = Arr::get($context, $key);
        if (is_null($contextValue)) {
            return false;
        }

        Log::info('contextValue', ['val' => $contextValue]);

        switch ($conditionType) {
            case 'equals':
                return $contextValue == $value;
            case 'not_equals':
                return $contextValue != $value;
            case 'greater_than':
                return $contextValue > $value;
            case 'less_than':
                return $contextValue < $value;
            case 'contains':
                return str_contains($contextValue, $value);
            case 'starts_with':
                return str_starts_with($contextValue, $value);
            case 'ends_with':
                return str_ends_with($contextValue, $value);
            case 'in_array':
                return in_array($contextValue, (array) $value);
            case 'not_in_array':
                return ! in_array($contextValue, (array) $value);
            default:
                return false;
        }
    }
}
