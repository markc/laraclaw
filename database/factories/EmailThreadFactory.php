<?php

namespace Database\Factories;

use App\Models\AgentSession;
use App\Models\EmailThread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailThread>
 */
class EmailThreadFactory extends Factory
{
    protected $model = EmailThread::class;

    public function definition(): array
    {
        return [
            'session_id' => AgentSession::factory(),
            'from_address' => fake()->safeEmail(),
            'to_address' => 'ai@kanary.org',
            'subject' => fake()->sentence(4),
            'message_id' => '<'.fake()->uuid().'@'.fake()->domainName().'>',
            'in_reply_to' => null,
            'references' => [],
            'direction' => 'inbound',
        ];
    }

    public function outbound(): static
    {
        return $this->state(fn () => [
            'from_address' => 'ai@kanary.org',
            'to_address' => fake()->safeEmail(),
            'direction' => 'outbound',
        ]);
    }

    public function inReplyTo(string $messageId): static
    {
        return $this->state(fn () => [
            'in_reply_to' => $messageId,
            'references' => [$messageId],
        ]);
    }
}
