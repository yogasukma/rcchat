<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'RCChat') }} API</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f8f9fa; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { background: white; padding: 30px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .title { font-size: 28px; font-weight: bold; color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; font-size: 16px; }
        .section { background: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { font-size: 20px; color: #333; margin-bottom: 15px; }
        .endpoint-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .endpoint-table th, .endpoint-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .endpoint-table th { background: #f8f9fa; font-weight: 600; }
        .method { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .method.post { background: #28a745; color: white; }
        .method.get { background: #007bff; color: white; }
        .method.delete { background: #dc3545; color: white; }
        .code { background: #f8f9fa; padding: 15px; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 14px; overflow-x: auto; margin: 10px 0; }
        .auth-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 6px; margin: 15px 0; }
        .feature-list { list-style: none; }
        .feature-list li { padding: 8px 0; }
        .feature-list li:before { content: "✓"; color: #28a745; font-weight: bold; margin-right: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="title">RCChat API</div>
            <div class="subtitle">AI-powered RunCloud management through conversational API</div>
        </div>

        <div class="section">
            <h2>API Endpoints</h2>
            <p><strong>Base URL:</strong> <code>{{ url('/') }}</code></p>

            <table class="endpoint-table">
                <tr>
                    <th>Method</th>
                    <th>Endpoint</th>
                    <th>Purpose</th>
                    <th>Request Body</th>
                    <th>Response</th>
                    <th>Auth</th>
                </tr>
                <tr>
                    <td><span class="method post">POST</span></td>
                    <td>/init</td>
                    <td>Initialize chat session</td>
                    <td><code>{"app_key": "string"}</code></td>
                    <td><code>{"status": "initialized", "roomId": "string", "userToken": "string"}</code></td>
                    <td>None</td>
                </tr>
                <tr>
                    <td><span class="method post">POST</span></td>
                    <td>/chats</td>
                    <td>Send message & get AI response</td>
                    <td><code>{"q": "string"}</code></td>
                    <td><code>{"a": "string", "actions": {}}</code></td>
                    <td>Required</td>
                </tr>
                <tr>
                    <td><span class="method get">GET</span></td>
                    <td>/chats</td>
                    <td>Get chat history</td>
                    <td>None</td>
                    <td><code>{"roomId": "string", "msgs": [], "app_key": "string"}</code></td>
                    <td>Required</td>
                </tr>
                <tr>
                    <td><span class="method delete">DELETE</span></td>
                    <td>/chats</td>
                    <td>Clear chat messages</td>
                    <td>None</td>
                    <td><code>{"status": "cleared", "message": "string"}</code></td>
                    <td>Required</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2>Authentication</h2>
            <div class="auth-box">
                <strong>Required Headers:</strong> All endpoints except <code>/init</code> require both <code>roomId</code> and <code>userToken</code>
            </div>

            <div class="code">Authorization: Bearer &lt;userToken&gt;
X-Room-Id: &lt;roomId&gt;</div>
        </div>

        <div class="section">
            <h2>Usage Examples</h2>

            <h3>1. Initialize Session</h3>
            <div class="code">curl -X POST {{ url('/init') }} \
  -H "Content-Type: application/json" \
  -d '{"app_key": "my-app-123"}'

# Response:
{"status":"initialized","roomId":"abc123","userToken":"xyz789..."}</div>

            <h3>2. Send RunCloud Commands</h3>
            <div class="code">curl -X POST {{ url('/chats') }} \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer xyz789..." \
  -H "X-Room-Id: abc123" \
  -d '{"q": "list my servers"}'

# Response:
{"a": "Here are your servers: ...", "actions": {}}</div>

            <h3>3. Get Chat History</h3>
            <div class="code">curl -X GET {{ url('/chats') }} \
  -H "Authorization: Bearer xyz789..." \
  -H "X-Room-Id: abc123"

# Response:
{"roomId": "abc123", "msgs": [...], "app_key": "my-app-123"}</div>
        </div>

        <div class="section">
            <h2>Example Commands</h2>
            <ul class="feature-list">
                <li>"List all my servers"</li>
                <li>"Show web applications on server 'production'"</li>
                <li>"Create backup for application 'my-app'"</li>
                <li>"List databases on server 'staging'"</li>
            </ul>
        </div>

        <div class="section">
            <h2>Features</h2>
            <ul class="feature-list">
                <li>AI-powered natural language processing with Gemini 2.5 Flash</li>
                <li>Autonomous multi-turn conversations (up to 5 turns)</li>
                <li>RunCloud-specific command filtering</li>
                <li>Token-based authentication</li>
                <li>MCP protocol for dynamic tool discovery</li>
            </ul>
        </div>
    </div>
</body>
</html>