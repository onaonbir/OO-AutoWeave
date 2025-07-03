<?php

namespace OnaOnbir\OOAutoWeave\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OnaOnbir\OOAutoWeave\Core\ContextManager;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\NodeHandlerResult;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\NodeRegistry;
use OnaOnbir\OOAutoWeave\Enums\NodeStatus;
use OnaOnbir\OOAutoWeave\Events\FlowNodeProcessed;
use OnaOnbir\OOAutoWeave\Models\Flow;
use OnaOnbir\OOAutoWeave\Models\FlowRun;
use Throwable;

class FlowRunService
{
    public ?ContextManager $contextManager = null;

    public function start(string $flowKey, ?Model $target = null, array $context = []): FlowRun
    {
        $flow = Flow::where('key', $flowKey)->firstOrFail();

        $run = FlowRun::create([
            'morphable_type' => $target?->getMorphClass(),
            'morphable_id' => $target?->getKey(),
            'name' => $flow->name,
            'base_structure' => $flow->structure,
            'node_states' => [],
            'context' => [],
            'status' => 'running',
        ]);

        $this->contextManager = ContextManager::bindToRun($run);
        $this->contextManager->start('global', null, $context);

        // Başlangıç node'larını işle
        foreach ($run->getStartNodes() as $node) {
            $this->startAndProcessNode($run, $node['key']);
        }

        return $run->fresh();
    }

    public function getContextManager(FlowRun $run): ContextManager
    {
        if (! $this->contextManager) {
            $this->contextManager = new ContextManager($run);
        }

        return $this->contextManager;
    }

    protected function startAndProcessNode(FlowRun $run, string $nodeKey): void
    {
        $node = $run->getNode($nodeKey);
        if (! $node) {
            throw new \Exception("Node '{$nodeKey}' bulunamadı.");
        }

        // Node'u başlat
        $this->startNode($run, $nodeKey);

        // Eğer otomatik işleme aktifse işle
        if ($node['auto_tick'] ?? false) {
            $this->processNode($run, $nodeKey);
        }
    }

    public function startNode(FlowRun $run, string $nodeKey): FlowRun
    {
        return DB::transaction(function () use ($run, $nodeKey) {
            $node = $run->getNode($nodeKey);

            if (! $node) {
                throw new \Exception("Node '{$nodeKey}' bulunamadı.");
            }

            $states = $run->node_states ?? [];

            // Node zaten işlenmişse tekrar başlatma
            if ($this->isNodeProcessed($states[$nodeKey]['status'] ?? null)) {
                return $run;
            }

            // Node başlarken initial context set edebiliriz
            if (isset($node['initial_context'])) {
                $this->contextManager->set('initial_context', $node['initial_context'], 'nodes', $nodeKey);
                $this->contextManager->persist();
                $run->fresh();
            }

            // Node'u işleniyor durumuna al
            $states[$nodeKey] = $this->makeNodeState(
                NodeStatus::Processing,
                $node,
                \Carbon\Carbon::now()->format('U.u')
            );

            $run->update(['node_states' => $states]);

            return $run->fresh();
        });
    }

    public function processNode(FlowRun $run, string $nodeKey): FlowRun
    {
        return DB::transaction(function () use ($run, $nodeKey) {
            $node = $run->getNode($nodeKey);
            if (! $node) {
                throw new \Exception("Node '{$nodeKey}' bulunamadı.");
            }

            $states = $run->node_states ?? [];

            $contextManager = $this->getContextManager($run);

            // Eğer node zaten tamamlanmışsa işleme
            if (($states[$nodeKey]['status'] ?? null) === NodeStatus::Completed->value) {
                return $run;
            }

            // Node'un başlatıldığından emin ol
            if (($states[$nodeKey]['status'] ?? null) !== NodeStatus::Processing->value) {
                $this->startNode($run, $nodeKey);
                $states = $run->fresh()->node_states;
            }

            try {

                // TODO Pre-execution context operations
                $contextManager->set('result_context.pre', [], 'nodes', $nodeKey);
                $contextManager->persist();

                $result = $this->executeNode($node);

                // Node durumunu güncelle
                $newStatus = $result->success ? NodeStatus::Completed : NodeStatus::Failed;
                $previous = $states[$nodeKey] ?? [];
                $startedAt = $previous['started_at'] ?? \Carbon\Carbon::now()->format('U.u');

                $states[$nodeKey] = $this->makeNodeState($newStatus, $node, $startedAt);

                // Context'i gelişmiş yöntemle güncelle
                $contextManager->set('result_context.on', $result->resultContext, 'nodes', $nodeKey);
                $contextManager->persist();

                // Override'ları uygula
                $states = $this->applyOverrides($run, $result, $states, $nodeKey);

                // TODO Post-execution context operations
                $contextManager->set('result_context.post', [], 'nodes', $nodeKey);
                $contextManager->persist();

                // Event fire et
                // event(new FlowNodeProcessed($run, $nodeKey, $result));

                // MUST BE FLUSH TEMPORARY
                $contextManager->flushTemp()->persist();



                // TODO Bİ GARİP OLDU...
                $existingStates = $run->node_states ?? [];
                $mergedStates = array_merge($existingStates, $states);
                $allCompleted = $this->checkAllNodesCompleted($run, $mergedStates);
                $run->update([
                    'node_states' => $mergedStates,
                    'status' => $allCompleted ? 'completed' : 'running',
                ]);

                // Başarılıysa ve otomatik ilerleme aktifse sonraki node'ları işle
                if ($result->success && ($node['auto_progress'] ?? false)) {
                    $this->processNextNodes($run, $nodeKey, $states);
                }

                return $run->fresh();

            } catch (Throwable $e) {
                $this->handleNodeError($run, $nodeKey, $e, $states);
                throw $e;
            }
        });
    }

    protected function processNextNodes(FlowRun $run, string $nodeKey, array $states): void
    {
        $edges = $run->getEdges();
        $nextNodes = $edges->where('connection.from', $nodeKey)->pluck('connection.to');

        foreach ($nextNodes as $nextKey) {
            // Conditional edge checking
            if ($this->shouldSkipEdge($run, $nodeKey, $nextKey)) {
                $this->skipNode($run, $nextKey, $states);

                continue;
            }

            // Eğer node zaten işlenmiş durumda ise atla
            if (in_array($states[$nextKey]['status'] ?? null, [
                NodeStatus::Completed->value,
                NodeStatus::Processing->value,
                NodeStatus::Skipped->value,
            ])) {
                continue;
            }

            $nextNode = $run->getNode($nextKey);
            if ($nextNode) {
                $this->startNode($run, $nextKey);

                if ($nextNode['auto_tick'] ?? false) {
                    $this->processNode($run, $nextKey);
                }
            }
        }

        $existingStates = $run->node_states ?? [];
        $mergedStates = array_merge($existingStates, $states);
        $run->update(['node_states' => $mergedStates]);
    }

    protected function shouldSkipEdge(FlowRun $run, string $fromKey, string $toKey): bool
    {
        $edge = $run->getEdges()->first(function ($edge) use ($fromKey, $toKey) {
            return $edge['connection']['from'] === $fromKey && $edge['connection']['to'] === $toKey;
        });

        if (! $edge || ! isset($edge['condition'])) {
            Log::debug("Edge [{$fromKey} → {$toKey}] has no condition, not skipping.");

            return false;
        }

        $manager = $this->getContextManager($run);
        $context = $manager->all();
        $result = FlowRun::matchCondition($edge['condition'], $context);

        Log::debug("Evaluating edge condition for [{$fromKey} → {$toKey}]:", [
            'condition' => $edge['condition'],
            'evaluated_context' => Arr::get($context, $edge['condition']['key'] ?? 'undefined'),
            'full_context' => $context,
            'matched' => $result,
        ]);

        return ! $result;
    }

    /**
     * Tüm node'ların tamamlanıp tamamlanmadığını kontrol eder
     */
    protected function checkAllNodesCompleted(FlowRun $run, array $states): bool
    {
        foreach ($run->getNodes() as $node) {
            $status = $states[$node['key']]['status'] ?? 'queued';
            if (! in_array($status, [NodeStatus::Completed->value, NodeStatus::Skipped->value])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Node error handling
     */
    protected function handleNodeError(FlowRun $run, string $nodeKey, Throwable $e, array &$states): void
    {
        $previous = $states[$nodeKey] ?? [];
        $startedAt = $previous['started_at'] ?? \Carbon\Carbon::now()->format('U.u');

        $states[$nodeKey] = $this->makeNodeState(
            NodeStatus::Failed,
            $run->getNode($nodeKey),
            $startedAt
        );

        // TODO
        // Error context'ini set et
        $manager = $this->getContextManager($run);
        $manager->setBatch([
            'last_error' => $e->getMessage(),
            'failed_node' => $nodeKey,
            'error_time' => \Carbon\Carbon::now()->format('U.u'),
        ], 'global');
        $manager->persist();

        $run->update([
            'node_states' => $states,
            'status' => 'error',
        ]);
    }

    public function processReadyNodes(FlowRun $run): FlowRun
    {
        foreach ($run->getReadyNodes() as $node) {
            $this->startNode($run, $node['key']);

            if ($node['auto_tick'] ?? false) {
                $this->processNode($run, $node['key']);
            }
        }

        return $run->fresh();
    }

    /*** MEVCUT YARDIMCI METODLAR ***/

    protected function isNodeProcessed(?string $status): bool
    {
        return in_array($status, [
            NodeStatus::Completed->value,
            NodeStatus::Processing->value,
            NodeStatus::Failed->value,
            NodeStatus::Skipped->value,
        ]);
    }

    /**
     * Node'u çalıştırır - artık Context Manager ile
     */
    protected function executeNode(array $node): NodeHandlerResult
    {
        return NodeRegistry::run($node['type'] ?? null, $node, $this->contextManager);
    }

    protected function makeNodeState(NodeStatus $status, array $snapshot = [], ?string $startedAt = null): array
    {
        // Kullanıcı tarafından başlatılan zamanı kullan veya mevcut zamanı al
        $now = microtime(true);
        $startedAt = $startedAt ?? $now;

        // Finish time (daha hassas timestamp)
        $finishedAt = in_array($status->value, [
            NodeStatus::Completed->value,
            NodeStatus::Failed->value,
            NodeStatus::Skipped->value,
        ]) ? $now : null;

        // Zaman farkı hesaplama (total_runtime)
        $totalRuntime = null;
        if ($finishedAt) {
            // totalRuntime, float olarak hesaplanacak
            $totalRuntime = $finishedAt - $startedAt;
            // Eğer negatif bir runtime varsa, sıfıra çekiyoruz
            $totalRuntime = max(0, $totalRuntime);
        }

        return [
            'status' => $status->value,
            'started_at' => (float) $startedAt, // float formatta
            'finished_at' => (float) $finishedAt, // float formatta
            'total_runtime' => (float) $totalRuntime, // float formatta
            'node' => [
                'snapshot' => $snapshot,
            ],
        ];
    }

    protected function applyOverrides(FlowRun $run, NodeHandlerResult $result, array $states, string $currentNodeKey): array
    {
        $edges = $run->getEdges();

        // next_node_key override
        if (! empty($result->overrides['next_node_key'])) {
            $nextKey = $result->overrides['next_node_key'];
            $nextNode = $run->getNode($nextKey);

            if ($nextNode) {
                // Diğer çıkışları skiple
                foreach ($edges->where('connection.from', $currentNodeKey) as $edge) {
                    $target = $edge['connection']['to'];
                    if ($target !== $nextKey && ! in_array($states[$target]['status'] ?? null, [
                        NodeStatus::Completed->value,
                        NodeStatus::Processing->value,
                        NodeStatus::Skipped->value,
                    ])) {
                        $this->skipNode($run, $target, $states);
                    }
                }
            }
        }

        // skip_nodes override
        if (! empty($result->overrides['skip_nodes'])) {
            foreach ($result->overrides['skip_nodes'] as $skipKey) {
                $this->skipNode($run, $skipKey, $states);
            }
        }

        return $states;
    }

    protected function skipNode(FlowRun $run, string $nodeKey, array &$states): void
    {
        if (! isset($states[$nodeKey]) || $states[$nodeKey]['status'] === NodeStatus::Queued->value) {
            $node = $run->getNode($nodeKey);
            if ($node) {
                $states[$nodeKey] = $this->makeNodeState(
                    NodeStatus::Skipped,
                    $node,
                    \Carbon\Carbon::now()->format('U.u')
                );
            }
        }
    }
}
