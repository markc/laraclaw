<?php

namespace App\Policies;

use App\Models\AgentSession;
use App\Models\User;

class AgentSessionPolicy
{
    public function view(User $user, AgentSession $session): bool
    {
        return $session->user_id === $user->id;
    }

    public function update(User $user, AgentSession $session): bool
    {
        return $session->user_id === $user->id;
    }

    public function delete(User $user, AgentSession $session): bool
    {
        return $session->user_id === $user->id;
    }
}
