<?php

namespace OnaOnbir\OOAutoWeave\Models\Traits;

use Illuminate\Support\Collection;

trait HasFlowStructure
{
    protected function getStructureFieldName(): string
    {
        return 'structure';
    }

    protected function getStructure(): array
    {
        return $this->{$this->getStructureFieldName()} ?? [];
    }

    protected function setStructure(array $structure): void
    {
        $this->{$this->getStructureFieldName()} = $structure;
    }



    //NODE HELPERS
    public function getNodes(): Collection
    {
        return collect($this->getStructure()['nodes'] ?? []);
    }

    public function getNode(string $key): ?array
    {
        return $this->getNodes()->firstWhere('key', $key);
    }

    public function getStartNodes(): Collection
    {
        $nodes = $this->getNodes();
        $edges = $this->getEdges();

        $nodesWithIncoming = $edges->pluck('connection.to')->unique()->all();

        return $nodes->filter(fn ($node) => ! in_array($node['key'], $nodesWithIncoming));
    }

    public function isStartNode(string $nodeKey): bool
    {
        $edges = $this->getEdges();

        return $edges->where('connection.to', $nodeKey)->isEmpty();
    }

    public function deleteNode(string $key): bool
    {
        $structure = $this->getStructure();

        $structure['nodes'] = collect($structure['nodes'] ?? [])
            ->reject(fn ($node) => $node['key'] === $key)
            ->values()
            ->toArray();

        $structure['edges'] = collect($structure['edges'] ?? [])
            ->reject(fn ($edge) => $edge['connection']['from'] === $key || $edge['connection']['to'] === $key)
            ->values()
            ->toArray();

        $this->setStructure($structure);
        return $this->save();
    }

    public function updateNode(string $key, array $newData): bool
    {
        $structure = $this->getStructure();

        $structure['nodes'] = collect($structure['nodes'] ?? [])
            ->map(function ($node) use ($key, $newData) {
                return $node['key'] === $key
                    ? array_merge($node, $newData)
                    : $node;
            })
            ->values()
            ->toArray();

        $this->setStructure($structure);
        return $this->save();
    }


    //EDGE HELPERS
    public function getEdges(): Collection
    {
        return collect($this->getStructure()['edges'] ?? []);
    }

    public function getEdgeByKey(string $key): ?array
    {
        return  $this->getEdges()->firstWhere('key', $key);
    }

    public function getEdgeByFromTo(string $from, string $to): ?array
    {
        return $this->getEdges()
            ->first(function ($edge) use ($from, $to) {
                return ($edge['connection']['from'] ?? null) === $from
                    && ($edge['connection']['to'] ?? null) === $to;
            });
    }

    public function deleteEdgeByKey(string $key): bool
    {
        $structure = $this->getStructure();

        $structure['edges'] = collect($structure['edges'] ?? [])
            ->reject(fn ($edge) => ($edge['key'] ?? null) === $key)
            ->values()
            ->toArray();

        $this->setStructure($structure);
        return $this->save();
    }

    public function updateEdgeByKey(string $key, array $newData): bool
    {
        $structure = $this->getStructure();

        $structure['edges'] = collect($structure['edges'] ?? [])
            ->map(function ($edge) use ($key, $newData) {
                return ($edge['key'] ?? null) === $key
                    ? array_merge($edge, $newData)
                    : $edge;
            })
            ->values()
            ->toArray();

        $this->setStructure($structure);
        return $this->save();
    }

    public function deleteEdgeByFromTo(string $from, string $to): bool
    {
        $structure = $this->getStructure();

        $structure['edges'] = collect($structure['edges'] ?? [])
            ->reject(function ($edge) use ($from, $to) {
                return ($edge['connection']['from'] ?? null) === $from
                    && ($edge['connection']['to'] ?? null) === $to;
            })
            ->values()
            ->toArray();

        $this->setStructure($structure);
        return $this->save();
    }

    public function updateEdgeByFromTo(string $from, string $to, array $newData): bool
    {
        $structure = $this->getStructure();

        $structure['edges'] = collect($structure['edges'] ?? [])
            ->map(function ($edge) use ($from, $to, $newData) {
                if (($edge['connection']['from'] ?? null) === $from &&
                    ($edge['connection']['to'] ?? null) === $to) {
                    return array_merge($edge, $newData);
                }
                return $edge;
            })
            ->values()
            ->toArray();

        $this->setStructure($structure);
        return $this->save();
    }
}
