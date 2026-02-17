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
// Channel uses dots (Pusher-safe) but session_key in DB uses colons
// Pattern: chat.session.web.{userId}.{uuid} maps to session_key web:{userId}:{uuid}
Broadcast::channel('chat.session.web.{userId}.{uuid}', function ($user, $userId, $uuid) {
    $sessionKey = "web:{$userId}:{$uuid}";
    $session = AgentSession::where('session_key', $sessionKey)->first();

    return ! $session || (int) $session->user_id === (int) $user->id;
});
