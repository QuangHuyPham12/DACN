<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerResetPassword extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Use the configured origin, never a client-supplied Host header.
        $url = rtrim(config('app.url'), '/').'/tai-khoan/dat-lai-mat-khau/'.$this->token.'?email='.rawurlencode($notifiable->email);

        return (new MailMessage)->subject('Đặt lại mật khẩu tài khoản SOLE')
            ->greeting('Chào bạn!')->line('SOLE nhận được yêu cầu đặt lại mật khẩu tài khoản của bạn.')
            ->action('Đặt lại mật khẩu', $url)->line('Liên kết chỉ dùng một lần và hết hạn sau 60 phút.')
            ->line('Nếu bạn không yêu cầu, hãy bỏ qua email này. Mật khẩu hiện tại vẫn được giữ nguyên.')
            ->salutation('SOLE Footwear');
    }
}
