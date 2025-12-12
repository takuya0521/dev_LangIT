<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminMfaCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code
    ) {}

    public function build()
    {
        return $this
            ->subject('【LangIT】管理者ログイン二段階認証コード')
            ->view('emails.admin_mfa_code')
            ->with([
                'code' => $this->code,
            ]);
    }
}
