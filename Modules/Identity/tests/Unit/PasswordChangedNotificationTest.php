<?php

namespace Modules\Identity\Tests\Unit;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Modules\Identity\Notifications\PasswordChangedNotification;
use Tests\TestCase;

class PasswordChangedNotificationTest extends TestCase
{
    public function test_password_change_notification_is_dispatched_to_the_queue(): void
    {
        $notification = new PasswordChangedNotification;
        $user = new User(['name' => 'Taylor User', 'email' => 'taylor@example.com']);

        Queue::fake();

        $user->notify($notification);

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        Queue::assertPushed(SendQueuedNotifications::class, function (SendQueuedNotifications $job) use ($user): bool {
            return $job->notification instanceof PasswordChangedNotification
                && $job->channels === ['mail']
                && $job->notifiables->first()->is($user);
        });
    }

    public function test_password_change_notification_sends_an_email(): void
    {
        $user = new User(['name' => 'Taylor User', 'email' => 'taylor@example.com']);
        $notification = new PasswordChangedNotification;

        config(['mail.default' => 'array']);
        Notification::sendNow($user, $notification);

        $transport = Mail::mailer()->getSymfonyTransport();

        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $this->assertCount(1, $transport->messages());

        $message = $transport->messages()->first()->getOriginalMessage();

        $this->assertSame('Your password has been changed', $message->getSubject());
        $this->assertSame('taylor@example.com', $message->getTo()[0]->getAddress());
        $this->assertStringContainsString(
            'If you did not make this change, contact support immediately.',
            $message->getTextBody(),
        );
    }
}
