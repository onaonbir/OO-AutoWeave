<?php

namespace OnaOnbir\OOAutoWeave\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OnaOnbir\OOAutoWeave\Models\Traits\JsonCast;

class Flow extends Model
{
    public function getTable(): string
    {
        return config('oo-auto-weave.tables.flows');
    }

    protected $fillable = [
        'key',
        'name',
        'structure',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'structure' => JsonCast::class,
        'metadata' => JsonCast::class,
        'is_active' => 'boolean',
    ];

    public function getNodes(): Collection
    {
        return collect($this->structure['nodes'] ?? []);
    }

    public function getEdges(): Collection
    {
        return collect($this->structure['edges'] ?? []);
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

    public function getNode(string $key): ?array
    {
        return collect($this->structure['nodes'] ?? [])
            ->firstWhere('key', $key);
    }

    public function getEdge(string $key): ?array
    {
        return collect($this->structure['edges'] ?? [])
            ->firstWhere('key', $key);
    }

    public function deleteNode(string $key): bool
    {
        $structure = $this->structure;

        // Node'u sil
        $structure['nodes'] = collect($structure['nodes'] ?? [])
            ->reject(fn ($node) => $node['key'] === $key)
            ->values()
            ->toArray();

        // Bağlantıları da sil
        $structure['edges'] = collect($structure['edges'] ?? [])
            ->reject(fn ($edge) => $edge['connection']['from'] === $key || $edge['connection']['to'] === $key
            )
            ->values()
            ->toArray();

        $this->structure = $structure;

        return $this->save();
    }

    public function updateNode(string $key, array $newData): bool
    {
        $structure = $this->structure;

        $structure['nodes'] = collect($structure['nodes'] ?? [])
            ->map(function ($node) use ($key, $newData) {
                return $node['key'] === $key
                    ? array_merge($node, $newData)
                    : $node;
            })
            ->values()
            ->toArray();

        $this->structure = $structure;

        return $this->save();
    }
}
