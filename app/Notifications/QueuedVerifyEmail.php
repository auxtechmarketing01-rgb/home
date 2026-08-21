<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * FR-AUTH-01's acceptance criterion is "verification email sent (**queued
 * job**)".
 *
 * Laravel's own VerifyEmail notification is not queued, so registering sent
 * the mail inline and the member waited on the mail transport before getting
 * their 201 back — a slow or unreachable SMTP host would have turned
 * registration into a timeout. Subclassing is all that is needed: the URL
 * generation and mail body stay the framework's.
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
