<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One email of an invoice or money receipt to a client: who it went to,
 * and whether the mail server took it (status sending → sent / failed).
 */
class SentMail extends Model
{
    public const KINDS = [
        'bandwidth_invoice' => 'Invoice',
        'receipt' => 'Money receipt',
        'settlement_invoice' => 'Invoice',
        'test' => 'Test mail',
    ];

    protected $fillable = ['document_type', 'document_id', 'kind', 'to', 'subject', 'status', 'error', 'user_id'];

    public function document()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sent(): bool
    {
        return $this->status === 'sent';
    }

    public function failed(): bool
    {
        return $this->status === 'failed';
    }

    /** Flash for the page that sent it: success, or why it failed. */
    public function flash(string $what): array
    {
        return $this->sent()
            ? ['success' => "{$what} emailed to {$this->to}."]
            : ['error' => "{$what} was not emailed: {$this->error}"];
    }

    /** Short line for a tooltip / status note, e.g. "Emailed to a@b.com · 13 Oct, 6:40 PM". */
    public function summary(): string
    {
        $when = $this->updated_at?->format('d M, h:i A');

        return match ($this->status) {
            'sent' => "Emailed to {$this->to} · {$when}",
            'failed' => "Email to {$this->to} failed · {$when}: {$this->error}",
            default => "Sending to {$this->to}…",
        };
    }
}
