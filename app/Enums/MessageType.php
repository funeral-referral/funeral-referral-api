<?php

namespace App\Enums;

enum MessageType: string
{
    case FirstEmail = 'first_email';
    case FollowUpOne = 'follow_up_1';
    case FollowUpTwo = 'follow_up_2';
    case CallScript = 'call_script';

    /**
     * Only emails are ever sent. A call script is read by a person who dials by hand.
     */
    public function isEmail(): bool
    {
        return $this !== self::CallScript;
    }

    public function position(): int
    {
        return match ($this) {
            self::FirstEmail => 1,
            self::FollowUpOne => 2,
            self::FollowUpTwo => 3,
            self::CallScript => 4,
        };
    }
}
