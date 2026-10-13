<?php

namespace App\Models\Concerns;

use App\Models\SentMail;

/**
 * An invoice or receipt that can be emailed to the client: its mails,
 * newest first, and the latest one for a status note.
 */
trait HasSentMails
{
    public function sentMails()
    {
        return $this->morphMany(SentMail::class, 'document')->latest('id');
    }

    public function lastMail()
    {
        return $this->morphOne(SentMail::class, 'document')->latestOfMany();
    }
}
