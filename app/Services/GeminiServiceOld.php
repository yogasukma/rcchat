<?php

namespace App\Services;

use App\Contracts\AIServiceInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService implements AIServiceInterface
{
    protected string $apiKey;

    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    protected RunCloudMCPService $mcpService;

    public function __construct(RunCloudMCPService $mcpService)
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->mcpService = $mcpService;

        if (empty($this->apiKey)) {
            throw new \Exception('Gemini API key is not configured. Please set GEMINI_API_KEY in your environment.');
        }
    }

    /**
     * Generate a response using Gemini API.
     */
    public function generateResponse(string $message): string
    {
        return $this->generateResponseWithContext($message);
    }

    /**
     * Generate a response with additional context (including MCP integration).
     */
    public function generateResponseWithContext(string $message, array $context = []): string
    {
        try {
            // Check if this might be RunCloud-related and we have context
            $enhancedMessage = $this->enhanceMessageWithRunCloudContext($message, $context);

            // Add instruction to prevent tool calling
            $finalMessage = $enhancedMessage."\n\nIMPORTANT: You are an AI assistant that provides direct answers. Do not attempt to call any tools or functions. Answer directly based on the information provided above.";

            info('Sending enhanced message to Gemini: '.$enhancedMessage);

            $response = Http::timeout(30)
                ->post($this->baseUrl.'/models/gemini-2.5-flash:generateContent?key='.$this->apiKey, [
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
                        'stopSequences' => [],
                    ],
                    'safetySettings' => [
                        [
                            'category' => 'HARM_CATEGORY_HARASSMENT',
                            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
                        ],
                        [
                            'category' => 'HARM_CATEGORY_HATE_SPEECH',
                            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
                        ],
                        [
                            'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
                        ],
                        [
                            'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->getFallbackResponse();
            }

            $data = $response->json();

            info('Gemini API response', ['response' => $data]);

            // Check for specific finish reasons that indicate tool call issues
            $finishReason = $data['candidates'][0]['finishReason'] ?? null;
            if ($finishReason === 'UNEXPECTED_TOOL_CALL') {
                Log::warning('Gemini tried to call a tool but none are configured', ['response' => $data]);

                // Try to extract any partial content before the tool call attempt
                if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    $partialText = trim($data['candidates'][0]['content']['parts'][0]['text']);
                    if (! empty($partialText)) {
                        return $partialText;
                    }
                }

                return "I found information about your RunCloud resources, but I'm having trouble formatting the response. Let me provide a direct answer based on what I found in your RunCloud context above.";
            }

            // Extract the generated text from Gemini response
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return trim($data['candidates'][0]['content']['parts'][0]['text']);
            }

            // Handle other finish reasons
            if ($finishReason === 'STOP') {
                // Normal completion but no text - this shouldn't happen
                Log::warning('Gemini finished normally but returned no text', ['response' => $data]);
            } elseif ($finishReason === 'MAX_TOKENS') {
                Log::warning('Gemini response was cut off due to max tokens', ['response' => $data]);

                return 'I was providing information about your RunCloud resources, but my response was cut off. Please ask me to continue or be more specific about what you need.';
            }

            Log::warning('Unexpected Gemini API response format', ['response' => $data]);

            return $this->getFallbackResponse();

        } catch (\Exception $e) {
            Log::error('Gemini API exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->getFallbackResponse();
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
            $runcloudToken = $context['runcloud_token'] ?? null;
            if ($runcloudToken && str_starts_with($runcloudToken, 'rc_') && $this->mcpService->isRunCloudRelated($message)) {
                return $message."\n\nI can see you're asking about RunCloud, but the MCP server connection isn't configured. Please set RC_MCP_TOKEN and RC_MCP_URL in your environment to enable RunCloud integration.";
            }

            return $message;
        }

        // Check if we have a RunCloud token in context
        $runcloudToken = $context['runcloud_token'] ?? null;
        if (! $runcloudToken) {
            // No token available, just add general RunCloud context
            return $message."\n\nNote: I can help you with RunCloud server management, but I'll need your RunCloud API token to access live data. You can provide it as your app_key when initializing the chat session.";
        }

        // Try to fetch relevant RunCloud context
        $runcloudContext = $this->fetchRunCloudContext($message, $runcloudToken);

        if ($runcloudContext) {
            return $message."\n\nRunCloud Context:\n".$runcloudContext;
        }

        // If we couldn't fetch context but this is RunCloud-related, inform user
        return $message."\n\nNote: I can help with RunCloud management using your token ".$runcloudToken.", but I'm unable to fetch live data right now. This could be due to an invalid token or MCP server connectivity issues.";
    }

    /**
     * Fetch relevant RunCloud context based on the message
     */
    protected function fetchRunCloudContext(string $message, string $runcloudToken): ?string
    {
        try {
            $intent = $this->analyzeRunCloudIntent($message);

            if (! $intent) {
                return null;
            }

            $contextParts = [];

            // Resolve server ID if only server name is provided
            $serverId = $intent['serverId'] ?? null;
            $serverName = $intent['serverName'] ?? null;

            if (! $serverId && $serverName) {
                $serverId = $this->resolveServerNameToId($runcloudToken, $serverName);
            }

            // Process each requested resource type
            foreach ($intent['resources'] as $resource) {
                switch ($resource) {
                    case 'servers':
                        $data = $this->mcpService->listServers($runcloudToken);
                        if ($data) {
                            $text = $this->mcpService->extractTextContent($data);
                            if ($text) {
                                $contextParts[] = "Your RunCloud Servers:\n".$text;
                            }
                        }
                        break;

                    case 'web_applications':
                        if ($serverId) {
                            $data = $this->mcpService->listWebApplications($runcloudToken, $serverId);
                            if ($data) {
                                $text = $this->mcpService->extractTextContent($data);
                                if ($text) {
                                    $serverLabel = $serverName ?: $serverId;
                                    $contextParts[] = "Web Applications on Server {$serverLabel} (ID: {$serverId}):\n".$text;
                                }
                            }
                        }
                        break;

                    case 'databases':
                        if ($serverId) {
                            $data = $this->mcpService->listDatabases($runcloudToken, $serverId);
                            if ($data) {
                                $text = $this->mcpService->extractTextContent($data);
                                if ($text) {
                                    $serverLabel = $serverName ?: $serverId;
                                    $contextParts[] = "Databases on Server {$serverLabel} (ID: {$serverId}):\n".$text;
                                }
                            }
                        }
                        break;

                    case 'backups':
                        $data = $this->mcpService->listBackups($runcloudToken);
                        if ($data) {
                            $text = $this->mcpService->extractTextContent($data);
                            if ($text) {
                                $contextParts[] = "Recent Backups:\n".$text;
                            }
                        }
                        break;
                }
            }

            return implode("\n\n", $contextParts);

        } catch (\Exception $e) {
            Log::error('Failed to fetch RunCloud context', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Use Gemini to resolve server name to ID
     */
    protected function resolveServerNameToId(string $runcloudToken, string $serverName): ?string
    {
        try {
            $servers = $this->mcpService->listServers($runcloudToken);
            if (! $servers) {
                return null;
            }

            $serversText = $this->mcpService->extractTextContent($servers);
            if (! $serversText) {
                return null;
            }

            $resolutionPrompt = "Given the server list below, find the server ID for the server name '{$serverName}'. Return only the numeric ID, nothing else.

Server List:
{$serversText}

Server name to find: {$serverName}

Return only the numeric server ID (e.g., 5979), or 'null' if not found:";

            $response = Http::timeout(10)
                ->post($this->baseUrl.'/models/gemini-2.5-flash:generateContent?key='.$this->apiKey, [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $resolutionPrompt,
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'maxOutputTokens' => 10,
                        'candidateCount' => 1,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Gemini server resolution failed', [
                    'status' => $response->status(),
                    'server_name' => $serverName,
                ]);

                return null;
            }

            $data = $response->json();

            if (! isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                Log::warning('Unexpected Gemini server resolution response', [
                    'response' => $data,
                    'server_name' => $serverName,
                ]);

                return null;
            }

            $result = trim($data['candidates'][0]['content']['parts'][0]['text']);

            if ($result === 'null' || ! is_numeric($result)) {
                Log::info('Server name not resolved by Gemini', [
                    'server_name' => $serverName,
                    'result' => $result,
                ]);

                return null;
            }

            Log::info('Gemini resolved server name to ID', [
                'input_name' => $serverName,
                'server_id' => $result,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Error resolving server name with Gemini', [
                'server_name' => $serverName,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Use Gemini to analyze user intent for RunCloud queries
     */
    protected function analyzeRunCloudIntent(string $message): ?array
    {
        try {
            $analysisPrompt = "Analyze this RunCloud-related query and extract the intent as JSON. Return only valid JSON with no additional text.

Query: \"{$message}\"

Extract:
1. resources: array of resource types the user is asking about (servers, web_applications, databases, backups)
2. serverId: specific server ID if mentioned (number only, or null if not found)
3. serverName: server name if mentioned (string, or null if not found)
4. action: what they want to do (list, show, get, check, etc.)

Available resource types:
- servers: for server listings or server info
- web_applications: for web apps, applications, sites
- databases: for database info
- backups: for backup information

Example responses:
{\"resources\": [\"servers\"], \"serverId\": null, \"serverName\": null, \"action\": \"list\"}
{\"resources\": [\"web_applications\"], \"serverId\": 123, \"serverName\": null, \"action\": \"show\"}
{\"resources\": [\"web_applications\"], \"serverId\": null, \"serverName\": \"yoga staging vultr\", \"action\": \"list\"}
{\"resources\": [\"databases\"], \"serverId\": 456, \"serverName\": \"nginx 22\", \"action\": \"list\"}

Only return the JSON object:";

            $response = Http::timeout(15)
                ->post($this->baseUrl.'/models/gemini-2.5-flash:generateContent?key='.$this->apiKey, [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $analysisPrompt,
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'maxOutputTokens' => 200,
                        'candidateCount' => 1,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Gemini intent analysis failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            if (! isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                Log::warning('Unexpected Gemini intent analysis response', ['response' => $data]);

                return null;
            }

            $intentText = trim($data['candidates'][0]['content']['parts'][0]['text']);

            // Clean up potential markdown formatting
            $intentText = preg_replace('/```json\s*/', '', $intentText);
            $intentText = preg_replace('/\s*```/', '', $intentText);

            $intent = json_decode($intentText, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Failed to parse Gemini intent analysis', [
                    'text' => $intentText,
                    'json_error' => json_last_error_msg(),
                ]);

                return null;
            }

            // Validate the response structure
            if (! isset($intent['resources']) || ! is_array($intent['resources'])) {
                Log::warning('Invalid intent analysis structure', ['intent' => $intent]);

                return null;
            }

            return $intent;

        } catch (\Exception $e) {
            Log::error('Error analyzing RunCloud intent', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get a fallback response when Gemini API fails.
     */
    protected function getFallbackResponse(): string
    {
        return "I apologize, but I'm having trouble generating a response right now. Please try again in a moment.";
    }

    /**
     * Check if the Gemini API is properly configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Get the name of the AI service provider.
     */
    public function getProviderName(): string
    {
        return 'Gemini';
    }
}
