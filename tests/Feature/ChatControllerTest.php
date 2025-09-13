<?php

use App\Models\ChatSession;
use Illuminate\Support\Facades\Http;

test('init creates session with app_key and returns userToken', function () {
    $response = $this->postJson('/init', ['app_key' => 'test-key-123']);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'status',
            'roomId',
            'userToken',
        ])
        ->assertJson([
            'status' => 'initialized',
        ]);

    // Verify database session was created
    $data = $response->json();
    $session = ChatSession::where('room_id', $data['roomId'])
        ->where('user_token', $data['userToken'])
        ->first();

    expect($session)->not->toBeNull();
    expect($session->app_key)->toBe('test-key-123');
    expect($session->expires_at->isAfter(now()))->toBeTrue();
});

test('init requires app_key parameter', function () {
    $response = $this->postJson('/init', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['app_key']);
});

test('send message returns fake response with valid token', function () {
    // Initialize session first
    $initResponse = $this->postJson('/init', ['app_key' => 'test-key-123']);
    $initData = $initResponse->json();

    $response = $this->postJson('/chats', [
        'q' => 'Hello, how are you?',
        'roomId' => $initData['roomId'],
        'userToken' => $initData['userToken'],
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'a',
            'actions',
        ]);

    // Verify database was updated
    $session = ChatSession::where('room_id', $initData['roomId'])->first();
    expect($session->messages)->toHaveCount(2); // question + answer
    expect($session->messages[0]->content)->toBe('Hello, how are you?');
    expect($session->messages[0]->type)->toBe('question');
    expect($session->messages[1]->type)->toBe('answer');
    expect($session->messages[1]->actions)->toBeArray();
});

test('send message requires valid token', function () {
    $response = $this->postJson('/chats', ['q' => 'Hello']);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Missing user token. Please provide token via Authorization header, X-User-Token header, or userToken parameter.',
        ]);
});

test('send message requires question parameter', function () {
    $initResponse = $this->postJson('/init', ['app_key' => 'test-key-123']);
    $initData = $initResponse->json();

    $response = $this->postJson('/chats', [
        'roomId' => $initData['roomId'],
        'userToken' => $initData['userToken'],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['q']);
});

test('send message rejects invalid token', function () {
    $response = $this->postJson('/chats', [
        'q' => 'Hello',
        'roomId' => 'fake-room',
        'userToken' => 'fake-token',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Invalid or expired session. Please initialize a new session.',
        ]);
});

test('get chats returns all messages with valid token', function () {
    // Initialize and send a message
    $initResponse = $this->postJson('/init', ['app_key' => 'test-key-123']);
    $initData = $initResponse->json();

    $this->postJson('/chats', [
        'q' => 'Test question',
        'roomId' => $initData['roomId'],
        'userToken' => $initData['userToken'],
    ]);

    $response = $this->get('/chats?'.http_build_query([
        'roomId' => $initData['roomId'],
        'userToken' => $initData['userToken'],
    ]));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'roomId',
            'msgs',
            'app_key',
        ])
        ->assertJson([
            'app_key' => 'test-key-123',
            'roomId' => $initData['roomId'],
        ]);

    $data = $response->json();
    expect($data['msgs'])->toHaveCount(2);
});

test('get chats requires valid token', function () {
    $response = $this->get('/chats');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Missing user token. Please provide token via Authorization header, X-User-Token header, or userToken parameter.',
        ]);
});

test('complete chat flow works end to end with tokens', function () {
    // 1. Initialize chat
    $initResponse = $this->postJson('/init', ['app_key' => 'my-app-123']);
    $initData = $initResponse->json();
    $roomId = $initData['roomId'];
    $userToken = $initData['userToken'];

    // 2. Send first message
    $this->postJson('/chats', [
        'q' => 'What is your name?',
        'roomId' => $roomId,
        'userToken' => $userToken,
    ]);

    // 3. Send second message
    $this->postJson('/chats', [
        'q' => 'How can you help me?',
        'roomId' => $roomId,
        'userToken' => $userToken,
    ]);

    // 4. Get all chats
    $response = $this->get('/chats?'.http_build_query([
        'roomId' => $roomId,
        'userToken' => $userToken,
    ]));

    $response->assertStatus(200);
    $data = $response->json();

    expect($data['roomId'])->toBe($roomId);
    expect($data['app_key'])->toBe('my-app-123');
    expect($data['msgs'])->toHaveCount(4); // 2 questions + 2 answers

    // Verify message structure
    expect($data['msgs'][0]['q'])->toBe('What is your name?');
    expect($data['msgs'][1])->toHaveKey('a');
    expect($data['msgs'][1])->toHaveKey('actions');

    expect($data['msgs'][2]['q'])->toBe('How can you help me?');
    expect($data['msgs'][3])->toHaveKey('a');
    expect($data['msgs'][3])->toHaveKey('actions');
});

test('delete chats clears messages successfully', function () {
    // Initialize and add some messages
    $initResponse = $this->postJson('/init', ['app_key' => 'test-clear-123']);
    $initData = $initResponse->json();

    $this->postJson('/chats', [
        'q' => 'Hello',
        'roomId' => $initData['roomId'],
        'userToken' => $initData['userToken'],
    ]);

    // Verify messages exist
    $response = $this->get('/chats?'.http_build_query([
        'roomId' => $initData['roomId'],
        'userToken' => $initData['userToken'],
    ]));
    $data = $response->json();
    expect($data['msgs'])->toHaveCount(2);

    // Clear the messages
    $clearResponse = $this->deleteJson('/chats', [
        'roomId' => $initData['roomId'],
        'userToken' => $initData['userToken'],
    ]);

    $clearResponse->assertStatus(200)
        ->assertJson([
            'status' => 'cleared',
            'message' => 'Chat session cleared successfully.',
        ]);

    // Verify messages are cleared but session still valid
    $getResponse = $this->get('/chats?'.http_build_query([
        'roomId' => $initData['roomId'],
        'userToken' => $initData['userToken'],
    ]));
    $getResponse->assertStatus(200);
    $data = $getResponse->json();
    expect($data['msgs'])->toHaveCount(0);
});

test('delete chats requires valid token', function () {
    $response = $this->deleteJson('/chats');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Missing user token. Please provide token via Authorization header, X-User-Token header, or userToken parameter.',
        ]);
});

test('clear and reinitialize workflow works with tokens', function () {
    // 1. Initialize
    $initResponse = $this->postJson('/init', ['app_key' => 'workflow-test']);
    $firstData = $initResponse->json();

    // 2. Send message
    $this->postJson('/chats', [
        'q' => 'First message',
        'roomId' => $firstData['roomId'],
        'userToken' => $firstData['userToken'],
    ]);

    // 3. Clear messages
    $this->deleteJson('/chats', [
        'roomId' => $firstData['roomId'],
        'userToken' => $firstData['userToken'],
    ])->assertStatus(200);

    // 4. Reinitialize with different key
    $newInitResponse = $this->postJson('/init', ['app_key' => 'new-workflow-test']);
    $secondData = $newInitResponse->json();

    // 5. Verify new session
    expect($secondData['roomId'])->not->toBe($firstData['roomId']);
    expect($secondData['userToken'])->not->toBe($firstData['userToken']);

    $response = $this->get('/chats?'.http_build_query([
        'roomId' => $secondData['roomId'],
        'userToken' => $secondData['userToken'],
    ]));
    $data = $response->json();

    expect($data['roomId'])->toBe($secondData['roomId']);
    expect($data['app_key'])->toBe('new-workflow-test');
    expect($data['msgs'])->toHaveCount(0); // Fresh session
});

test('token expires after 3 hours', function () {
    // Initialize session
    $initResponse = $this->postJson('/init', ['app_key' => 'expire-test']);
    $initData = $initResponse->json();

    // Travel forward in time past expiration
    $this->travel(4)->hours();

    // Try to use expired token
    $response = $this->postJson('/chats', [
        'q' => 'This should fail',
        'roomId' => $initData['roomId'],
        'userToken' => $initData['userToken'],
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Invalid or expired session. Please initialize a new session.',
        ]);
});

test('authentication works with Authorization header', function () {
    // Initialize session
    $initResponse = $this->postJson('/init', ['app_key' => 'header-test']);
    $initData = $initResponse->json();

    // Use Authorization Bearer header
    $response = $this->postJson('/chats', [
        'q' => 'Test with header',
        'roomId' => $initData['roomId'],
    ], [
        'Authorization' => 'Bearer '.$initData['userToken'],
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['a', 'actions']);
});

test('authentication works with X-User-Token header', function () {
    // Initialize session
    $initResponse = $this->postJson('/init', ['app_key' => 'x-header-test']);
    $initData = $initResponse->json();

    // Use X-User-Token header
    $response = $this->postJson('/chats', [
        'q' => 'Test with X-Token header',
    ], [
        'X-User-Token' => $initData['userToken'],
        'X-Room-Id' => $initData['roomId'],
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['a', 'actions']);
});

it('sends message to AI service and returns AI response', function () {
    // Mock Gemini API response
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => 'Hello! I am an AI assistant. How can I help you today?',
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    // Initialize session
    $initResponse = $this->postJson('/init', ['app_key' => 'gemini-test']);
    $initData = $initResponse->json();

    // Send message
    $response = $this->postJson('/chats', [
        'q' => 'Hello, what are you?',
    ], [
        'Authorization' => 'Bearer '.$initData['userToken'],
        'X-Room-Id' => $initData['roomId'],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'a' => 'Hello! I am an AI assistant. How can I help you today?',
            'actions' => [],
        ]);

    // Verify HTTP call was made to Gemini
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'gemini-1.5-flash:generateContent') &&
               $request->data()['contents'][0]['parts'][0]['text'] === 'Hello, what are you?';
    });
});
