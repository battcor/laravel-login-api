<?php

namespace Modules\Identity\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $expiresIn = config('auth.verification.expire', 60);
        $url = URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes($expiresIn),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        return (new MailMessage)
            ->subject('Verify your email address')
            ->line('Please verify your email address to activate your account.')
            ->action('Verify email address', $url)
            ->line("This link expires in {$expiresIn} minutes.");
    }
}
