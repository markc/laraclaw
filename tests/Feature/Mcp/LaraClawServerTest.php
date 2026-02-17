<?php

use App\Mcp\Prompts\ChatPrompt;
use App\Mcp\Resources\AgentResource;
use App\Mcp\Servers\LaraClawServer;
use App\Mcp\Tools\ListSessionsTool;
use App\Mcp\Tools\ReadSessionTool;
use App\Models\Agent;
use App\Models\AgentMessage;
use App\Models\AgentSession;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->agent = Agent::create([
        'name' => 'Test Agent',
        'slug' => 'test',
        'model' => 'claude-sonnet-4-5-20250929',
        'provider' => 'anthropic',
        'is_default' => true,
    ]);
});

test('list-sessions returns user sessions', function () {
    $session = AgentSession::create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'session_key' => 'mcp:'.$this->user->id.':test-session',
        'title' => 'Test Chat',
        'channel' => 'mcp',
        'trust_level' => 'operator',
        'last_activity_at' => now(),
    ]);

    $response = LaraClawServer::actingAs($this->user)
        ->tool(ListSessionsTool::class);

    $response->assertOk()
        ->assertSee('Test Chat')
        ->assertSee($session->session_key);
});

test('list-sessions does not return other users sessions', function () {
    $otherUser = User::factory()->create();

    AgentSession::create([
        'agent_id' => $this->agent->id,
        'user_id' => $otherUser->id,
        'session_key' => 'mcp:'.$otherUser->id.':other-session',
        'title' => 'Secret Chat',
        'channel' => 'mcp',
        'trust_level' => 'operator',
        'last_activity_at' => now(),
    ]);

    $response = LaraClawServer::actingAs($this->user)
        ->tool(ListSessionsTool::class);

    $response->assertOk()
        ->assertDontSee('Secret Chat');
});

test('list-sessions requires authentication', function () {
    $response = LaraClawServer::tool(ListSessionsTool::class);

    $response->assertHasErrors(['Authentication required.']);
});

test('read-session returns messages for owned session', function () {
    $session = AgentSession::create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'session_key' => 'mcp:'.$this->user->id.':read-test',
        'title' => 'Read Test',
        'channel' => 'mcp',
        'trust_level' => 'operator',
        'last_activity_at' => now(),
    ]);

    AgentMessage::create([
        'session_id' => $session->id,
        'role' => 'user',
        'content' => 'Hello agent',
    ]);

    AgentMessage::create([
        'session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'Hello human',
    ]);

    $response = LaraClawServer::actingAs($this->user)
        ->tool(ReadSessionTool::class, [
            'session_key' => $session->session_key,
        ]);

    $response->assertOk()
        ->assertSee('Hello agent')
        ->assertSee('Hello human');
});

test('read-session denies access to other users session', function () {
    $otherUser = User::factory()->create();

    $session = AgentSession::create([
        'agent_id' => $this->agent->id,
        'user_id' => $otherUser->id,
        'session_key' => 'mcp:'.$otherUser->id.':private',
        'title' => 'Private Chat',
        'channel' => 'mcp',
        'trust_level' => 'operator',
        'last_activity_at' => now(),
    ]);

    $response = LaraClawServer::actingAs($this->user)
        ->tool(ReadSessionTool::class, [
            'session_key' => $session->session_key,
        ]);

    $response->assertHasErrors(['Session not found or access denied.']);
});

test('read-session requires authentication', function () {
    $response = LaraClawServer::tool(ReadSessionTool::class, [
        'session_key' => 'mcp:1:some-session',
    ]);

    $response->assertHasErrors(['Authentication required.']);
});

test('agent resource lists available agents', function () {
    $response = LaraClawServer::resource(AgentResource::class);

    $response->assertOk()
        ->assertSee('Test Agent')
        ->assertSee('test');
});

test('chat prompt generates conversation starter', function () {
    $response = LaraClawServer::prompt(ChatPrompt::class, [
        'topic' => 'Laravel MCP integration',
    ]);

    $response->assertOk()
        ->assertSee('Laravel MCP integration');
});

test('chat prompt includes context when provided', function () {
    $response = LaraClawServer::prompt(ChatPrompt::class, [
        'topic' => 'debugging',
        'context' => 'WebSocket connection drops after 30 seconds',
    ]);

    $response->assertOk()
        ->assertSee('debugging')
        ->assertSee('WebSocket connection drops after 30 seconds');
});
