<?php

namespace OnaOnbir\OOAutoWeave\Core\DefaultNodes;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OnaOnbir\OOAutoWeave\Core\ContextManager;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\BaseNodeHandler;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\NodeHandlerResult;

class HttpRequestNode extends BaseNodeHandler
{
    public function handle(array $node, ContextManager $manager): NodeHandlerResult
    {
        $url = $node['attributes']['url'] ?? null;
        $method = strtoupper($node['attributes']['method'] ?? 'GET');
        $body = $node['attributes']['body'] ?? [];
        $headers = $node['attributes']['headers'] ?? [];

        if (! $url) {
            return NodeHandlerResult::error(message: 'HTTP request requires URL');
        }

        sleep(3);

        try {
            Log::info('HttpRequestNode triggered', [
                'url' => $url,
                'method' => $method,
                'body' => $body,
                'headers' => $headers,
                'node' => $node,
            ]);

            $http = Http::withHeaders($headers);

            $response = match ($method) {
                'POST' => $http->post($url, $body),
                'PUT' => $http->put($url, $body),
                'PATCH' => $http->patch($url, $body),
                'DELETE' => $http->delete($url, $body),
                default => $http->get($url),
            };

            $data = [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->json() ?? $response->body(),
                'url' => $url,
                'method' => $method,
                'timestamp' => now()->toIso8601String(),
            ];

            return NodeHandlerResult::success([
                'status' => 'http_request_completed',
                'response' => $data,
            ]);
        } catch (\Exception $e) {
            return NodeHandlerResult::error(message: 'HTTP request failed: '.$e->getMessage());
        }
    }

    public static function definition(): array
    {
        return [
            'type' => 'http_request',
            'attributes' => ['icon' => 'globe'],
        ];
    }
}
