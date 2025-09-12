# RCChat 

A example application demonstrating a complete Chat API with session-based messaging, dummy AI responses, and comprehensive Artisan command interface.

## 🚀 Features

- **RESTful Chat API** with 4 endpoints
- **Session-based messaging** with persistent storage
- **Dummy AI responses** using Laravel Faker
- **Comprehensive Artisan commands** for CLI interaction
- **Laravel 12** with streamlined architecture
- **Pest v4** testing with browser testing capabilities
- **Laravel Boost MCP** integration for AI-enhanced development
- **Tailwind CSS 4.0** with Vite for modern frontend

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

| Method | Endpoint | Purpose | Request Body | Response |
|--------|----------|---------|--------------|----------|
| POST | `/init` | Initialize chat session | `{"app_key": "string"}` | `{"status": "initialized", "roomId": "string"}` |
| POST | `/chats` | Send message & get AI response | `{"q": "string"}` | `{"a": "string", "actions": {}}` |
| GET | `/chats` | Get chat history | - | `{"roomId": "string", "msgs": [], "app_key": "string"}` |
| DELETE | `/chats` | Clear chat session | - | `{"status": "cleared", "message": "string"}` |

### API Usage Examples

```bash
# Initialize session
curl -X POST http://localhost:8000/init \
  -H "Content-Type: application/json" \
  -d '{"app_key": "my-app-123"}'

# Send message
curl -X POST http://localhost:8000/chats \
  -H "Content-Type: application/json" \
  -d '{"q": "Hello, how are you?"}'

# Get history
curl -X GET http://localhost:8000/chats

# Clear session
curl -X DELETE http://localhost:8000/chats
```

## 🎮 Artisan Commands

Interact with the Chat API directly from the command line:

### Available Commands

```bash
# Initialize chat session
php artisan chat:init "my-api-key"

# Send a message
php artisan chat:send "Hello, how can you help me?"
php artisan chat:send "What is Laravel?" --json  # JSON output only

# Get chat history
php artisan chat:get                    # Formatted display
php artisan chat:get --json            # JSON output

# Clear chat session
php artisan chat:clear

# Get command help
php artisan help chat:init
```

### Example Workflow

```bash
# 1. Initialize
php artisan chat:init "demo-key-2024"
# ✅ Chat session initialized successfully!
# Room ID: abc123xyz
# Status: initialized

# 2. Send messages
php artisan chat:send "What is Laravel?"
# ✅ Message sent successfully!
# JSON Response: {"a": "Laravel is...", "actions": {...}}
# AI Response: Laravel is a web application framework...

# 3. Get history
php artisan chat:get
# ✅ Chat History Retrieved Successfully!
# Session Info: Room ID: abc123xyz, Total Messages: 2
# [➤ User] What is Laravel?
# [⚙️ AI] Laravel is a web application framework...

# 4. Clear session
php artisan chat:clear
# ✅ Chat session cleared successfully!
# Room ID: abc123xyz (deleted)
# Messages cleared: 2
```

## 🧪 Testing

The application includes comprehensive test coverage:

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ChatControllerTest.php

# Run with filter
php artisan test --filter="chat"

# Combined test script (clears config first)
composer run test
```

### Test Coverage
- ✅ Session initialization and validation
- ✅ Message sending and response generation
- ✅ Chat history retrieval
- ✅ Session clearing functionality
- ✅ Error handling and edge cases
- ✅ Complete end-to-end workflows

**Test Results**: 11 tests, 52 assertions - All passing ✅

## 🏗 Architecture

### Laravel 12 Features
- **Streamlined Structure**: No middleware files in `app/Http/Middleware/`
- **Auto-registering Commands**: Commands in `app/Console/Commands/` automatically available
- **Bootstrap Configuration**: Middleware and exceptions in `bootstrap/app.php`
- **Modern Casts**: Model casts using `casts()` method

### Key Components

- **`ChatController`**: Handles HTTP requests for chat operations
- **`ChatSessionManager`**: Manages session persistence with file-based storage
- **Artisan Commands**: CLI interface for chat operations
  - `ChatInitCommand`, `ChatSendCommand`, `ChatGetCommand`, `ChatClearCommand`
- **Faker Integration**: Generates realistic dummy AI responses and actions

### Session Storage

Chat sessions are stored in `storage/app/chat_session.json` with the structure:

```json
{
    "roomId": "abc123xyz",
    "msgs": [
        {"q": "user question"},
        {"a": "ai answer", "actions": {"action_key": "action_description"}}
    ],
    "app_key": "your-api-key"
}
```

## 🔧 Development

### Code Quality

```bash
# Format code with Laravel Pint
vendor/bin/pint --dirty

# Run specific tests during development
php artisan test --filter=ChatController
```

### Laravel Boost MCP Integration

This application includes Laravel Boost for AI-enhanced development:

- **MCP Server**: `php artisan boost:mcp`
- **Configuration**: `.mcp.json` and `.cursor/mcp.json`
- **Enhanced Tools**: Documentation search, browser logs, debugging tools

### Development Workflow

1. **Start Development**: `composer run dev`
2. **Make Changes**: Edit code with HMR watching
3. **Run Tests**: `php artisan test --filter=relevant`
4. **Format Code**: `vendor/bin/pint --dirty`
5. **Commit Changes**: All tests passing + formatted code

## 📚 Documentation

For detailed development guidance, see:
- **[WARP.md](WARP.md)** - Complete development guide for Warp AI
- **[CLAUDE.md](CLAUDE.md)** - Laravel Boost guidelines and best practices
- **[Laravel Documentation](https://laravel.com/docs)** - Official Laravel 12 docs

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

### Development Standards
- Follow Laravel Boost guidelines (see CLAUDE.md)
- Write comprehensive tests for new features
- Use Laravel Pint for code formatting
- Maintain backwards compatibility for API endpoints

## 📄 License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
