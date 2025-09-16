<?php

namespace App\Services;

use App\Contracts\AIServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService implements AIServiceInterface
{
    protected string $apiKey;

    protected string $baseUrl;

    protected RunCloudMCPService $mcpService;

    protected ?string $currentRunCloudToken = null;

    public function __construct(RunCloudMCPService $mcpService)
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->mcpService = $mcpService;

        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('Gemini API key not configured');
        }
    }

    /**
     * Centralized method to call Gemini API
     */
    protected function callGeminiAPI(array $payload): \Illuminate\Http\Client\Response
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/models/gemini-2.5-flash:generateContent?key=' . $this->apiKey, $payload);
    }

    /**
     * Generate a simple response without context
     */
    public function generateResponse(string $message): string
    {
        return $this->generateResponseWithContext($message, []);
    }

    /**
     * Generate AI response with optional RunCloud context
     */
    public function generateResponseWithContext(string $message, array $context = []): string
    {
        try {
            // Check if this is a RunCloud-related query first
            if (!$this->mcpService->isRunCloudRelated($message)) {
                return "I'm a RunCloud management assistant. I can help with servers, web applications, databases, and backups. Please ask RunCloud-related questions.";
            }

            // Store RunCloud token for multi-turn conversations
            $this->currentRunCloudToken = $context['runcloud_token'] ?? null;

            // Check if this might be RunCloud-related and we have context
            $enhancedMessage = $this->enhanceMessageWithRunCloudContext($message, $context);

            // Only add anti-tool instruction if no RunCloud context
            if (! isset($context['runcloud_token'])) {
                $finalMessage = $enhancedMessage . "\n\nIMPORTANT: You are an AI assistant that provides direct answers. Do not attempt to call any tools or functions.";
            } else {
                $finalMessage = $enhancedMessage;
            }

            info('Sending enhanced message to Gemini: ' . $enhancedMessage);

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $finalMessage,
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 1500,
                    'candidateCount' => 1,
                ],
            ];

            // Auto-discover and add MCP tools if we have RunCloud context
            if (isset($context['runcloud_token'])) {
                $tools = $this->getAutoDiscoveredTools();
                if ($tools) {
                    $payload['tools'] = [$tools];
                    Log::info('Auto-discovered tools for Gemini', ['tools' => $tools]);
                }
            }

            $response = $this->callGeminiAPI($payload);

            if (! $response->successful()) {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->getFallbackResponse($message, $context);
            }

            $data = $response->json();

            info('Gemini API response', ['response' => $data]);

            // Handle tool calls if Gemini wants to call functions
            if (isset($data['candidates'][0]['content']['parts'][0]['functionCall'])) {
                $functionCall = $data['candidates'][0]['content']['parts'][0]['functionCall'];
                $toolResponse = $this->handleToolCall($functionCall, $context['runcloud_token'] ?? '');

                // Make a second call to Gemini with the tool response
                return $this->continueWithToolResponse($finalMessage, $functionCall, $toolResponse);
            }

            // Check for specific finish reasons that indicate tool call issues
            $finishReason = $data['candidates'][0]['finishReason'] ?? null;
            if ($finishReason === 'UNEXPECTED_TOOL_CALL') {
                Log::warning('Gemini tried to call a tool but none are configured', ['response' => $data]);

                return "I found information about your RunCloud resources, but I'm having trouble formatting the response.";
            }

            // Extract the generated text from Gemini response
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return trim($data['candidates'][0]['content']['parts'][0]['text']);
            }

            Log::warning('Unexpected Gemini response structure', ['response' => $data]);

            return $this->getFallbackResponse($message, $context);
        } catch (\Exception $e) {
            Log::error('Gemini service exception', [
                'message' => $e->getMessage(),
            ]);

            return $this->getFallbackResponse($message, $context);
        }
    }

    /**
     * Enhance message with RunCloud context if applicable
     */
    protected function enhanceMessageWithRunCloudContext(string $message, array $context = []): string
    {
        // Debug logging
        Log::info('GEMINI DEBUG: Checking RunCloud context', [
            'message' => $message,
            'context' => $context,
            'mcp_configured' => $this->mcpService->isConfigured(),
            'is_runcloud_related' => $this->mcpService->isRunCloudRelated($message),
        ]);

        // Check if MCP is configured and this seems RunCloud-related
        if (! $this->mcpService->isConfigured() || ! $this->mcpService->isRunCloudRelated($message)) {
            // If MCP not configured but we have a RunCloud token, inform the user
            if (isset($context['runcloud_token'])) {
                return $message . "\n\nNote: I can help with RunCloud management using your token " . $context['runcloud_token'] . ", but I'm unable to fetch live data right now. This could be due to an invalid token or MCP server connectivity issues.";
            }

            return $message;
        }

        $runcloudToken = $context['runcloud_token'] ?? null;
        if (! $runcloudToken) {
            return $message;
        }

        // For RunCloud queries, provide general autonomous behavior guidance
        $guidance = "\n\nYou are an autonomous RunCloud management agent with access to RunCloud tools. Analyze the request, determine what information you need, use the available tools to gather that information autonomously. For read-only operations (list, find, show), complete them directly. For write operations (create, delete, modify), you may ask for confirmation before proceeding, but never ask users for information you can find yourself using the available tools.";

        return $message . $guidance;
    }

    /**
     * Check if the AI service is properly configured
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Get the name of the AI service provider
     */
    public function getProviderName(): string
    {
        return 'Gemini';
    }

    /**
     * Auto-discover MCP tools and convert to Gemini function declarations
     */
    protected function getAutoDiscoveredTools(): ?array
    {
        try {
            $mcpTools = $this->mcpService->listTools();
            if (! $mcpTools || ! isset($mcpTools['tools'])) {
                return null;
            }

            $functionDeclarations = [];

            foreach ($mcpTools['tools'] as $tool) {
                $parameters = $tool['inputSchema'] ?? [
                    'type' => 'object',
                    'properties' => (object) [],
                ];

                // Convert empty arrays to empty objects for Gemini compatibility
                if (isset($parameters['properties']) && is_array($parameters['properties']) && empty($parameters['properties'])) {
                    $parameters['properties'] = (object) [];
                }

                $functionDeclarations[] = [
                    'name' => str_replace('-', '_', $tool['name']), // Convert kebab-case to snake_case
                    'description' => $tool['description'] ?? '',
                    'parameters' => $parameters,
                ];
            }

            return [
                'functionDeclarations' => $functionDeclarations,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to auto-discover MCP tools', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Handle tool calls from Gemini
     */
    protected function handleToolCall(array $functionCall, string $runcloudToken): array
    {
        $functionName = $functionCall['name'] ?? '';
        $args = $functionCall['args'] ?? [];

        // Convert snake_case back to kebab-case for MCP
        $mcpToolName = str_replace('_', '-', $functionName);

        try {
            // Call the MCP tool directly with all provided arguments
            $result = $this->mcpService->callTool($mcpToolName, $args, $runcloudToken);

            return ['success' => (bool) $result, 'data' => $result];
        } catch (\Exception $e) {
            Log::error('MCP tool call failed', [
                'tool' => $mcpToolName,
                'args' => $args,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'Tool call failed: ' . $e->getMessage()];
        }
    }

    /**
     * Continue conversation with tool response, allowing multiple tool calls
     */
    protected function continueWithToolResponse(string $originalMessage, array $functionCall, array $toolResponse): string
    {
        $toolResultText = '';
        if ($toolResponse['success']) {
            $toolResultText = $this->mcpService->extractTextContent($toolResponse['data']);
        } else {
            $toolResultText = $toolResponse['error'] ?? 'Tool call failed';
        }

        // Build conversation history with function call and response
        $contents = [
            [
                'role' => 'user',
                'parts' => [['text' => $originalMessage]],
            ],
            [
                'role' => 'model',
                'parts' => [[
                    'functionCall' => [
                        'name' => $functionCall['name'],
                        'args' => (object) ($functionCall['args'] ?? []),
                    ],
                ]],
            ],
            [
                'role' => 'function',
                'parts' => [[
                    'functionResponse' => [
                        'name' => $functionCall['name'],
                        'response' => (object) ['result' => $toolResultText],
                    ],
                ]],
            ],
        ];

        return $this->continueMultiTurnConversation($contents, $originalMessage);
    }

    /**
     * Handle multi-turn conversation with potential additional tool calls
     */
    protected function continueMultiTurnConversation(array $contents, string $originalMessage, int $maxTurns = 5): string
    {
        $turnCount = 0;

        while ($turnCount < $maxTurns) {
            $payload = [
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 1500,
                    'candidateCount' => 1,
                ],
            ];

            // Add tools for potential additional calls
            $tools = $this->getAutoDiscoveredTools();
            if ($tools) {
                $payload['tools'] = [$tools];
            }

            $response = $this->callGeminiAPI($payload);

            if (! $response->successful()) {
                Log::error('Multi-turn conversation failed', [
                    'turn' => $turnCount,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                break;
            }

            $data = $response->json();

            // Check if Gemini wants to call another function
            if (isset($data['candidates'][0]['content']['parts'][0]['functionCall'])) {
                $functionCall = $data['candidates'][0]['content']['parts'][0]['functionCall'];

                // Add model's function call to conversation
                $contents[] = [
                    'role' => 'model',
                    'parts' => [[
                        'functionCall' => [
                            'name' => $functionCall['name'],
                            'args' => (object) ($functionCall['args'] ?? []),
                        ],
                    ]],
                ];

                // Execute the tool
                $toolResponse = $this->handleToolCall($functionCall, $this->getCurrentRunCloudToken());
                $toolResultText = '';
                if ($toolResponse['success']) {
                    $toolResultText = $this->mcpService->extractTextContent($toolResponse['data']);
                } else {
                    $toolResultText = $toolResponse['error'] ?? 'Tool call failed';
                }

                // Add function response to conversation
                $contents[] = [
                    'role' => 'function',
                    'parts' => [[
                        'functionResponse' => [
                            'name' => $functionCall['name'],
                            'response' => (object) ['result' => $toolResultText],
                        ],
                    ]],
                ];

                $turnCount++;
                Log::info('Multi-turn: Called tool', ['turn' => $turnCount, 'tool' => $functionCall['name']]);

                continue;
            }

            // Gemini returned a text response, we're done
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                Log::info('Multi-turn conversation completed', ['turns' => $turnCount]);

                return trim($data['candidates'][0]['content']['parts'][0]['text']);
            }

            // No function call and no text, something's wrong
            break;
        }

        Log::warning('Multi-turn conversation hit max turns or failed', ['turns' => $turnCount]);

        return 'I was able to gather information but had trouble completing the full response. Please try asking more specifically.';
    }

    /**
     * Get current RunCloud token from context (helper for multi-turn)
     */
    protected function getCurrentRunCloudToken(): string
    {
        // This is a bit of a hack - we'll need to pass the token through the call chain
        // For now, we'll store it as a class property when the conversation starts
        return $this->currentRunCloudToken ?? '';
    }

    /**
     * Get a fallback response when Gemini API fails.
     */
    protected function getFallbackResponse(string $message, array $context = []): string
    {
        // Check if we have RunCloud context to provide a more specific message
        if (isset($context['runcloud_token']) && $this->mcpService->isRunCloudRelated($message)) {
            return "I'm sorry, but I'm having trouble connecting to the Gemini AI service right now (the service appears to be overloaded). Please try your RunCloud request again in a few moments.";
        }

        return "I apologize, but I'm having trouble generating a response right now. Please try again in a moment.";
    }
}
