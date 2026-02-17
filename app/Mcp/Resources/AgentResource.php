<?php

namespace App\Mcp\Resources;

use App\Models\Agent;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

class AgentResource extends Resource
{
    /**
     * The resource's description.
     */
    protected string $description = 'List all available AI agents and their configurations.';

    /**
     * The resource's URI.
     */
    protected string $uri = 'laraclaw://agents';

    /**
     * The resource's MIME type.
     */
    protected string $mimeType = 'application/json';

    /**
     * Handle the resource request.
     */
    public function handle(Request $request): Response
    {
        $agents = Agent::all(['id', 'name', 'slug', 'model', 'provider', 'is_default']);

        return Response::text(json_encode($agents, JSON_THROW_ON_ERROR));
    }
}
