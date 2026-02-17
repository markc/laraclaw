<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateMcpToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:token {email : The email address of the user to create a token for}
                            {--name=mcp : The name for the token}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a Sanctum API token for MCP client authentication';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("User not found: {$this->argument('email')}");

            return self::FAILURE;
        }

        $token = $user->createToken($this->option('name'));

        $this->info('MCP token created successfully.');
        $this->newLine();
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->comment('Add this to your MCP client configuration as: Authorization: Bearer <token>');

        return self::SUCCESS;
    }
}
