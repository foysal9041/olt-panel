<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * One or more email addresses separated by commas, semicolons or spaces —
 * each one has to be a real address.
 */
class EmailList implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $fail("\"{$email}\" is not a valid email address.");

                return;
            }
        }
    }
}
