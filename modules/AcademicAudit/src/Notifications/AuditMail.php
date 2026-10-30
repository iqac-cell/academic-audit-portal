<?php

namespace Mmmut\AcademicAudit\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Plain workflow email: subject, a few lines and a button back to the portal. */
class AuditMail extends Notification
{
    public function __construct(
        public string $subject,
        public array $lines,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->greeting('Dear '.($notifiable->name ?? 'Sir/Madam').',');
        foreach ($this->lines as $line) {
            $mail->line($line);
        }
        if ($this->actionUrl) {
            $mail->action($this->actionText ?? 'Open portal', $this->actionUrl);
        }
        return $mail->salutation("Regards,\nIQAC, MMMUT Gorakhpur");
    }
}
