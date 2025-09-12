<?php

test('init creates session with app_key', function () {
    $response = $this->postJson('/init', ['app_key' => 'test-key-123']);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'roomId',
        ])
        ->assertJson([
            'status' => 'initialized',
        ]);
        
    // Verify session was created
    expect(session('roomId'))->not->toBeNull();
    expect(session('msgs'))->toBe([]);
    expect(session('app_key'))->toBe('test-key-123');
});

test('init requires app_key parameter', function () {
    $response = $this->postJson('/init', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['app_key']);
});

test('send message returns fake response', function () {
    // Initialize session first
    $this->postJson('/init', ['app_key' => 'test-key-123']);

    $response = $this->postJson('/chats', ['q' => 'Hello, how are you?']);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'a',
            'actions',
        ]);

    // Verify session was updated
    $msgs = session('msgs');
    expect($msgs)->toHaveCount(2);
    expect($msgs[0]['q'])->toBe('Hello, how are you?');
    expect($msgs[1])->toHaveKey('a');
    expect($msgs[1])->toHaveKey('actions');
});

test('send message requires session initialization', function () {
    $response = $this->postJson('/chats', ['q' => 'Hello']);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Session not initialized. Please call /init first.',
        ]);
});

test('send message requires question parameter', function () {
    $this->postJson('/init', ['app_key' => 'test-key-123']);

    $response = $this->postJson('/chats', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['q']);
});

test('get chats returns all session data', function () {
    // Initialize and send a message
    $this->postJson('/init', ['app_key' => 'test-key-123']);
    $this->postJson('/chats', ['q' => 'Test question']);

    $response = $this->get('/chats');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'roomId',
            'msgs',
            'app_key',
        ])
        ->assertJson([
            'app_key' => 'test-key-123',
        ]);

    $data = $response->json();
    expect($data['msgs'])->toHaveCount(2);
});

test('get chats requires session initialization', function () {
    $response = $this->get('/chats');

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Session not initialized. Please call /init first.',
        ]);
});

test('complete chat flow works end to end', function () {
    // 1. Initialize chat
    $initResponse = $this->postJson('/init', ['app_key' => 'my-app-123']);
    $roomId = $initResponse->json('roomId');

    // 2. Send first message
    $this->postJson('/chats', ['q' => 'What is your name?']);

    // 3. Send second message
    $this->postJson('/chats', ['q' => 'How can you help me?']);

    // 4. Get all chats
    $response = $this->get('/chats');

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

test('delete chats clears session successfully', function () {
    // Initialize and add some messages
    $this->postJson('/init', ['app_key' => 'test-clear-123']);
    $this->postJson('/chats', ['q' => 'Hello']);
    
    // Verify messages exist
    $response = $this->get('/chats');
    $data = $response->json();
    expect($data['msgs'])->toHaveCount(2);
    
    // Clear the session
    $clearResponse = $this->deleteJson('/chats');
    
    $clearResponse->assertStatus(200)
        ->assertJson([
            'status' => 'cleared',
            'message' => 'Chat session cleared successfully.',
        ]);
    
    // Verify session is cleared
    $getResponse = $this->get('/chats');
    $getResponse->assertStatus(400)
        ->assertJson([
            'error' => 'Session not initialized. Please call /init first.',
        ]);
});

test('delete chats fails when no session exists', function () {
    $response = $this->deleteJson('/chats');
    
    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Session not initialized. Nothing to clear.',
        ]);
});

test('clear and reinitialize workflow works', function () {
    // 1. Initialize
    $initResponse = $this->postJson('/init', ['app_key' => 'workflow-test']);
    $firstRoomId = $initResponse->json('roomId');
    
    // 2. Send message
    $this->postJson('/chats', ['q' => 'First message']);
    
    // 3. Clear session
    $this->deleteJson('/chats')->assertStatus(200);
    
    // 4. Reinitialize with different key
    $newInitResponse = $this->postJson('/init', ['app_key' => 'new-workflow-test']);
    $secondRoomId = $newInitResponse->json('roomId');
    
    // 5. Verify new session
    expect($secondRoomId)->not->toBe($firstRoomId);
    
    $response = $this->get('/chats');
    $data = $response->json();
    
    expect($data['roomId'])->toBe($secondRoomId);
    expect($data['app_key'])->toBe('new-workflow-test');
    expect($data['msgs'])->toHaveCount(0); // Fresh session
});
