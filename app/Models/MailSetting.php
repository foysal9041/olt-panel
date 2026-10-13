<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * The office's outgoing mail account (company domain SMTP), used to email
 * invoices and money receipts to clients. One row, edited on Settings → Email.
 */
class MailSetting extends Model
{
    use LogsActivity {
        loggableChanges as baseLoggableChanges;
    }

    /** SSL on 465, or STARTTLS on 587 — what hosting mail servers offer. */
    public const ENCRYPTIONS = [
        'ssl' => ['label' => 'SSL (port 465)', 'port' => 465],
        'tls' => ['label' => 'STARTTLS (port 587)', 'port' => 587],
    ];

    protected $fillable = ['enabled', 'host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name', 'reply_to', 'bcc'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'port' => 'integer',
            'password' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['enabled' => false, 'port' => 465, 'encryption' => 'ssl']);
    }

    /** Everything needed to log in and send is filled in. */
    public function complete(): bool
    {
        return filled($this->host) && $this->port && filled($this->username) && filled($this->password) && filled($this->from_address);
    }

    /** Switched on and complete: invoices and receipts can be emailed. */
    public function ready(): bool
    {
        return $this->enabled && $this->complete();
    }

    /**
     * Office copies, e.g. the accounts mailbox: a hidden copy of every mail.
     *
     * @return list<string>
     */
    public function bccList(): array
    {
        return Customer::parseEmails($this->bcc);
    }

    /** Settings for Mail::build(): an SMTP mailer for just this account. */
    public function mailerConfig(): array
    {
        return [
            'transport' => 'smtp',
            'scheme' => $this->encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'timeout' => 30,
            'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: null,
        ];
    }

    /** Log that the password changed — but never the password. */
    protected function loggableChanges(): array
    {
        $changes = $this->baseLoggableChanges();
        if ($this->wasChanged('password')) {
            $changes['password'] = ['old' => '•••', 'new' => '(changed)'];
        }

        return $changes;
    }

    protected function activityLogLabel(): string
    {
        return 'Email Settings';
    }

    protected function activityLogTitle(): string
    {
        return 'Outgoing mail (SMTP)';
    }

    protected function activityLogExcept(): array
    {
        return ['password', 'created_at', 'updated_at'];
    }
}
