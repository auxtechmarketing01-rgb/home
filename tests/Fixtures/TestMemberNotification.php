<?php

namespace Tests\Fixtures;

use App\Notifications\MemberNotification;

/**
 * A minimal concrete MemberNotification. Phase 1 ships no product
 * notification of its own, so the delivery contract is exercised against
 * this fixture rather than against a class that would have to be invented.
 */
class TestMemberNotification extends MemberNotification
{
    public function __construct(private string $message = 'Something happened.') {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->message,
            'action_url' => '/goals',
        ];
    }
}
