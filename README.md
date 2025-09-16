# RCChat - RunCloud Management Assistant

**RCChat** is a Laravel-based AI chatbot that provides autonomous RunCloud server management through natural language conversations.

## ✨ Key Features

- **🤖 AI-Powered**: Uses Google's Gemini 2.5 Flash for intelligent conversation processing
- **☁️ RunCloud Integration**: Direct API integration for managing servers, web applications, databases, and backups
- **🔧 MCP Protocol**: Leverages Model Context Protocol for dynamic tool discovery and execution
- **🔄 Multi-turn Conversations**: Autonomous agent that can chain multiple operations (up to 5 turns)
- **🎯 Smart Filtering**: Only processes RunCloud-related queries, rejecting general chat requests
- **🔐 Token-based Authentication**: Secure, stateless API architecture

## 🚀 Core Functionality

- **Server Management**: List and manage RunCloud servers
- **Web Applications**: Create and manage web applications
- **Database Operations**: Handle database management tasks
- **Backup Management**: Create and manage backups

## 🏗️ Architecture

- **GeminiService**: Handles AI conversations and tool orchestration
- **RunCloudMCPService**: Manages RunCloud API calls via MCP protocol
- **Auto-discovery**: Runtime discovery of available RunCloud tools
- **Secure Access**: Token-based authentication for secure API access

The system acts as an intelligent intermediary between users and RunCloud infrastructure, enabling complex server management tasks through simple conversational commands like:
- *"List all my servers"*
- *"Create a backup for application 'my-app'"*
- *"Show web applications on server 'production'"*

## 📦 Installation

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js and npm

### Setup

```bash
# Clone the repository
git clone <repository-url> rcchat
cd rcchat

# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database setup
touch database/database.sqlite
php artisan migrate:fresh --seed

# Start development servers
composer run dev  # Runs server, queue, logs, and vite concurrently
```

## 🌐 API Endpoints

### Base URL: `http://localhost:8000`

| Method | Endpoint | Purpose | Authentication | Request Body | Response |
|--------|----------|---------|------------------|--------------|----------|
| POST | `/init` | Initialize chat session | None | `{"app_key": "string"}` | `{"status": "initialized", "roomId": "string", "userToken": "string"}` |
| POST | `/chats` | Send message & get AI response | Required | `{"q": "string"}` + auth | `{"a": "string", "actions": {}}` |
| GET | `/chats` | Get chat history | Required | None + auth | `{"roomId": "string", "msgs": [], "app_key": "string"}` |
| DELETE | `/chats` | Clear chat messages | Required | None + auth | `{"status": "cleared", "message": "string"}` |

### 🔐 Authentication Methods

All endpoints except `/init` require both `roomId` and `userToken`. You can provide these via:

1. **Headers** (Recommended):
   ```bash
   Authorization: Bearer <userToken>
   X-Room-Id: <roomId>
   ```

2. **Alternative Headers**:
   ```bash
   X-User-Token: <userToken>
   X-Room-Id: <roomId>
   ```

3. **Request Body/Query Parameters**:
   ```json
   {
     "userToken": "<userToken>",
     "roomId": "<roomId>",
     "q": "your message"
   }
   ```

### 📋 API Usage Examples

```bash
# 1. Initialize session (returns userToken)
curl -X POST http://localhost:8000/init \
  -H "Content-Type: application/json" \
  -d '{"app_key": "my-app-123"}'

# Response: {"status":"initialized","roomId":"abc123","userToken":"xyz789..."}

# 2. Send message (using Authorization header - recommended)
curl -X POST http://localhost:8000/chats \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer xyz789..." \
  -H "X-Room-Id: abc123" \
  -d '{"q": "Hello, how are you?"}'

# 3. Get chat history
curl -X GET http://localhost:8000/chats \
  -H "Authorization: Bearer xyz789..." \
  -H "X-Room-Id: abc123"

# 4. Clear messages (keeps session active)
curl -X DELETE http://localhost:8000/chats \
  -H "Authorization: Bearer xyz789..." \
  -H "X-Room-Id: abc123"

# Alternative: Using request body parameters
curl -X POST http://localhost:8000/chats \
  -H "Content-Type: application/json" \
  -d '{
    "q": "Hello!",
    "userToken": "xyz789...",
    "roomId": "abc123"
  }'
```

### ⚠️ Error Responses

```json
// Missing token
{"error": "Missing user token. Please provide token via Authorization header, X-User-Token header, or userToken parameter."}

// Invalid/expired token
{"error": "Invalid or expired session. Please initialize a new session."}

// Missing room ID
{"error": "Missing room ID. Please provide room ID via X-Room-Id header or roomId parameter."}
```

### Development Standards
- Follow Laravel Boost guidelines (see CLAUDE.md)
- Write comprehensive tests for new features
- Use Laravel Pint for code formatting

## 📄 License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
