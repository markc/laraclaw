<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\ChatPrompt;
use App\Mcp\Resources\AgentResource;
use App\Mcp\Resources\SessionResource;
use App\Mcp\Tools\ChatTool;
use App\Mcp\Tools\ListSessionsTool;
use App\Mcp\Tools\ReadSessionTool;
use Laravel\Mcp\Server;

class LaraClawServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'LaRaClaw';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.1.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        LaRaClaw is a self-hosted AI agent platform. Use the chat tool to send messages and get AI responses.
        Use list-sessions and read-session to browse conversation history. Each session belongs to a user and
        is identified by a session key. Sessions are created automatically when you send the first message.
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        ChatTool::class,
        ListSessionsTool::class,
        ReadSessionTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        SessionResource::class,
        AgentResource::class,
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        ChatPrompt::class,
    ];
}
