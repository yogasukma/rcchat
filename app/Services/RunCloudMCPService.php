<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunCloudMCPService
{
    protected string $mcpToken;

    protected string $mcpUrl;

    public function __construct()
    {
        $this->mcpToken = config('services.runcloud_mcp.token');
        $this->mcpUrl = config('services.runcloud_mcp.url');

        if (empty($this->mcpToken) || empty($this->mcpUrl)) {
            Log::warning('RunCloud MCP service not configured properly');
        }
    }

    /**
     * Call an MCP tool with the given parameters
     */
    public function callTool(string $toolName, array $arguments, string $runcloudToken): ?array
    {
        if (! $this->isConfigured()) {
            Log::error('RunCloud MCP service not configured');

            return null;
        }

        try {
            $payload = [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => [
                    'name' => $toolName,
                    'arguments' => array_merge($arguments, [
                        'runcloud_api_token' => $runcloudToken,
                    ]),
                ],
            ];

            $headers = [
                'Authorization' => 'Bearer ' . $this->mcpToken,
                'X-RunCloud-Token' => $runcloudToken,
                'Content-Type' => 'application/json',
            ];

            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($this->mcpUrl, $payload);

            if (! $response->successful()) {
                Log::error('MCP API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            // Check for JSON-RPC errors
            if (isset($data['error'])) {
                Log::error('MCP tool error', [
                    'error' => $data['error'],
                ]);

                return null;
            }

            return $data['result'] ?? null;
        } catch (\Exception $e) {
            Log::error('MCP API exception', [
                'message' => $e->getMessage(),
                'tool' => $toolName,
            ]);

            return null;
        }
    }


    /**
     * List available tools from the MCP server
     */
    public function listTools(): ?array
    {
        if (! $this->isConfigured()) {
            Log::error('RunCloud MCP service not configured for tool discovery');

            return null;
        }

        try {
            $payload = [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/list',
                'params' => [],
            ];

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->mcpToken,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->mcpUrl, $payload);

            if (! $response->successful()) {
                Log::error('MCP tools/list error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            if (isset($data['error'])) {
                Log::error('MCP tools/list error', ['error' => $data['error']]);

                return null;
            }

            return $data['result'] ?? null;
        } catch (\Exception $e) {
            Log::error('MCP tools/list exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Check if the MCP service is configured
     */
    public function isConfigured(): bool
    {
        return ! empty($this->mcpToken) && ! empty($this->mcpUrl);
    }

    /**
     * Extract text content from MCP result
     */
    public function extractTextContent(?array $result): ?string
    {
        if (! $result || ! isset($result['content'])) {
            return null;
        }

        $textParts = [];
        foreach ($result['content'] as $content) {
            if ($content['type'] === 'text') {
                $textParts[] = $content['text'];
            }
        }

        return implode("\n", $textParts);
    }

    /**
     * Determine if a message might be RunCloud-related
     */
    public function isRunCloudRelated(string $message): bool
    {
        // Core RunCloud concepts based on available tools
        $keywords = [
            'server',
            'servers',
            'application',
            'applications',
            'app',
            'webapp',
            'database',
            'backup',
            'backups',
            'runcloud'
        ];

        $message = strtolower($message);

        foreach ($keywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
