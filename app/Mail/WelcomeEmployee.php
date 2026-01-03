<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class WelcomeEmployee extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public ?string $rawPassword;
    public ?string $inviteLink;

    /**
     * Create a new message instance.
     *
     * @param User $user
     * @param string|null $rawPassword
     * @param string|null $inviteLink
     */
    public function __construct(User $user, ?string $rawPassword = null, ?string $inviteLink = null)
    {
        $this->user = $user;
        $this->rawPassword = $rawPassword;
        $this->inviteLink = $inviteLink;
    }

    public function build()
    {
        return $this->subject('Welcome to ' . config('app.name'))
                    ->markdown('emails.welcome_employee')
                    ->with([
                        'user' => $this->user,
                        'rawPassword' => $this->rawPassword,
                        'inviteLink' => $this->inviteLink,
                        'appName' => config('app.name'),
                    ]);
    }
}
