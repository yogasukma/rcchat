# RCChat API Server

A token-based Chat API server built with Laravel 12, featuring secure authentication, persistent storage, and dummy AI responses. Fully transformed from session-based to stateless API architecture.

## 🚀 Features

- **🔒 Token-based Authentication** with 3-hour expiry
- **🌐 RESTful API Server** with 4 secure endpoints
- **💾 Database Storage** with SQLite/PostgreSQL support
- **🤖 Dummy AI Responses** using Laravel Faker
- **⏰ Automatic Token Cleanup** via scheduled commands
- **🧪 Comprehensive Testing** with Pest v4
- **🔧 Laravel 12** with modern architecture
- **📱 Multiple Auth Methods** (Headers, Bearer tokens, query params)

## 🛠 Tech Stack

- **Backend**: Laravel 12, PHP 8.2+
- **Frontend**: Tailwind CSS 4.0, Vite
- **Database**: SQLite (default), MySQL/PostgreSQL support
- **Testing**: Pest v4 with browser testing
- **Code Quality**: Laravel Pint
- **AI Tooling**: Laravel Boost MCP Server

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
