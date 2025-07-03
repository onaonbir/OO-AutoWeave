<?php

namespace OnaOnbir\OOAutoWeave\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use OnaOnbir\OOAutoWeave\Enums\NodeStatus;
use OnaOnbir\OOAutoWeave\Models\Traits\HasFlowStructure;
use OnaOnbir\OOAutoWeave\Models\Traits\JsonCast;

class FlowRun extends Model
{

    use HasFlowStructure;

    protected function getStructureFieldName(): string
    {
        return 'base_structure';
    }

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
}
