<?php

use App\Models\AgentSession;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// User-level channel for session lifecycle events (sidebar updates)
Broadcast::channel('chat.user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Session-level channel for stream events (chat messages)
Broadcast::channel('chat.session.{sessionKey}', function ($user, $sessionKey) {
    // Allow if session doesn't exist yet (new chat) or user owns the session
    $session = AgentSession::where('session_key', $sessionKey)->first();

    return ! $session || (int) $session->user_id === (int) $user->id;
});
