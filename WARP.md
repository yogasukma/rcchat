# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

This is a Laravel 12 application built with modern development practices and tools:
- **Framework**: Laravel 12 with PHP 8.2+
- **Frontend**: Tailwind CSS 4.0 with Vite bundling
- **Testing**: Pest v4 with browser testing capabilities
- **Database**: SQLite (default), supports MySQL/PostgreSQL
- **MCP Integration**: Laravel Boost for enhanced AI tooling
- **Architecture**: Follows Laravel 12's streamlined structure

## Essential Development Commands

### Initial Setup
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite  # Creates SQLite database
php artisan migrate:fresh --seed
```

### Development Server
```bash
# Runs all development services concurrently (server, queue, logs, vite)
composer run dev

# Individual services
php artisan serve            # Web server (localhost:8000)
npm run dev                  # Vite dev server with HMR
php artisan queue:listen     # Queue worker
php artisan pail             # Real-time log viewer
```

### Testing
```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Filter by test name
php artisan test --filter=testName

# Browser tests (Pest v4)
php artisan test tests/Browser/

# Combined test script (clears config first)
composer run test
```

### Code Quality
```bash
# Format code with Laravel Pint
vendor/bin/pint --dirty

# Format all files
vendor/bin/pint
```

### Frontend Build
```bash
npm run build              # Production build
npm run dev               # Development with HMR
```

### Database Operations
```bash
php artisan migrate:fresh --seed    # Reset database with seeders
php artisan migrate                  # Run pending migrations
php artisan db:seed                  # Run seeders only
```

## High-Level Architecture

This application follows Laravel 12's streamlined architecture:

### Key Structural Changes (Laravel 12)
- **No `app/Http/Middleware/`**: Middleware registered in `bootstrap/app.php`
- **No `app/Console/Kernel.php`**: Console configuration in `bootstrap/app.php` or `routes/console.php`
- **Auto-registering commands**: Files in `app/Console/Commands/` automatically available
- **Service providers**: Application-specific providers in `bootstrap/providers.php`

### Application Flow
```
HTTP Request → bootstrap/app.php → Routes → Controllers → Services/Actions → Models → Database
                     ↓
              Middleware & Exception Handling
```

### Directory Structure
- `app/Http/Controllers/`: HTTP request handlers
- `app/Models/`: Eloquent models with relationships
- `app/Providers/`: Service providers (see `bootstrap/providers.php`)
- `resources/views/`: Blade templates
- `resources/js/`: JavaScript/TypeScript frontend code
- `resources/css/`: Stylesheets (Tailwind CSS)
- `database/migrations/`: Schema definitions
- `database/factories/`: Model factories for testing
- `database/seeders/`: Database seeding
- `tests/Feature/`: Feature tests
- `tests/Unit/`: Unit tests
- `tests/Browser/`: Browser tests (Pest v4)

### Frontend Architecture
- **Build Tool**: Vite with Laravel plugin
- **CSS Framework**: Tailwind CSS 4.0 with @tailwindcss/vite plugin
- **Entry Points**: `resources/css/app.css` and `resources/js/app.js`
- **HMR**: Enabled for development with file watching

### Database & Queues
- **Default**: SQLite for simplicity
- **Queue Driver**: Database-based queuing
- **Sessions**: Database-stored sessions
- **Cache**: Database-backed caching

## Laravel Boost MCP Integration

This application includes Laravel Boost, an MCP server providing AI-enhanced development tools:

### MCP Configuration
- **Server**: `php artisan boost:mcp` 
- **Config Files**: `.mcp.json` and `.cursor/mcp.json`

### Key Boost Guidelines
- **Artisan Commands**: Use `list-artisan-commands` tool to verify available parameters
- **URL Generation**: Use `get-absolute-url` tool for correct scheme/domain/port
- **Debugging**: Use `tinker` tool for PHP execution and `database-query` for DB reads
- **Documentation**: Use `search-docs` tool for version-specific Laravel ecosystem docs
- **Browser Logs**: Use `browser-logs` tool to read recent browser errors/exceptions

### Code Standards (from Boost Guidelines)
- **PHP 8 Constructor Promotion**: Use in `__construct()` methods
- **Type Declarations**: Always use explicit return types and parameter hints
- **Form Requests**: Create Form Request classes for validation vs inline validation
- **Eloquent**: Prefer relationships over raw queries; prevent N+1 with eager loading
- **Testing**: Use factories; write Pest tests with proper assertions
- **Formatting**: Run `vendor/bin/pint --dirty` before finalizing changes

## Testing Strategy

### Pest v4 Features
This application uses Pest v4 with advanced testing capabilities:

- **Browser Testing**: Real browser automation in `tests/Browser/`
- **Smoke Testing**: Multi-page testing with `visit(['/', '/about'])`
- **Visual Regression**: Screenshot comparison testing
- **Test Sharding**: Parallel test execution

### Test Structure
```php path=null start=null
// Feature Test Example
test('user can view homepage', function () {
    $response = $this->get('/');
    
    $response->assertSuccessful();
});

// Browser Test Example  
test('user can navigate site', function () {
    $page = visit('/');
    
    $page->assertSee('Welcome')
         ->assertNoJavascriptErrors()
         ->click('About')
         ->assertSee('About Page');
});
```

### Testing Best Practices
- Write feature tests for user workflows
- Use unit tests for isolated logic
- Leverage browser tests for JavaScript interactions
- Test happy paths, failure paths, and edge cases
- Use model factories for test data
- Mock external services appropriately

## Troubleshooting

### Common Issues

**Vite Manifest Error**
```bash
# If you see "Unable to locate file in Vite manifest"
npm run build
# or for development
npm run dev
```

**Frontend Changes Not Reflecting**
- Ensure `npm run dev` is running for HMR
- Try `npm run build` for production assets
- Check browser cache and hard refresh

**Database Issues**
```bash
# Reset database completely
php artisan migrate:fresh --seed

# Check database file exists (SQLite)
ls -la database/database.sqlite
```

**Queue Not Processing**
```bash
# Start queue worker
php artisan queue:listen --tries=1

# Check failed jobs
php artisan queue:failed
```

### Environment Setup
- Ensure PHP 8.2+ is installed
- Node.js for frontend tooling
- SQLite enabled in PHP (default setup)
- Composer installed globally

## Development Workflow

1. **Start Development**: Run `composer run dev` to start all services
2. **Make Changes**: Edit code with HMR watching for frontend changes
3. **Run Tests**: Use `php artisan test --filter=relevant` for targeted testing
4. **Format Code**: Run `vendor/bin/pint --dirty` before commits
5. **Check Browser**: Use browser testing for UI validation

This architecture emphasizes Laravel conventions, modern PHP practices, and comprehensive testing with AI-enhanced development tools through Laravel Boost MCP integration.
