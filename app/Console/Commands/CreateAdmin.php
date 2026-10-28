<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The first admin of a fresh install (install.sh runs it), or a way back in:
 * an existing username gets the new password and admin rights again.
 */
class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin
        {--username= : Login username}
        {--name= : Full name (default: the username)}
        {--email= : Email (default: username@localhost)}
        {--password= : Password (asked for when left out; at least 8 characters)}
        {--password-stdin : Read the password from standard input (keeps it out of the process list)}';

    protected $description = 'Create an admin user, or reset an existing user\'s password and make them admin';

    public function handle()
    {
        $username = Str::lower(trim((string) ($this->option('username') ?: $this->ask('Username', 'admin'))));
        if (! preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
            $this->error('The username needs 3–50 letters, digits, dots, dashes or underscores.');

            return Command::FAILURE;
        }

        $password = $this->option('password-stdin')
            ? rtrim((string) stream_get_contents(STDIN), "\r\n")
            : (string) ($this->option('password') ?: $this->secret('Password (at least 8 characters)'));
        if (mb_strlen($password) < 8) {
            $this->error('The password needs at least 8 characters.');

            return Command::FAILURE;
        }

        $user = User::where('username', $username)->first();
        $email = $this->option('email') ?: ($user->email ?? $username . '@localhost');
        if (User::where('email', $email)->when($user, fn ($q) => $q->whereKeyNot($user->id))->exists()) {
            $this->error("Another user already has the email {$email}; pass a different --email.");

            return Command::FAILURE;
        }

        $user ??= new User(['username' => $username]);
        $user->forceFill([
            'name' => $this->option('name') ?: ($user->name ?? $username),
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'status' => true,
            'olt_access' => 'all',
            'switch_access' => 'all',
        ])->save();

        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated') . " admin \"{$username}\".");

        return Command::SUCCESS;
    }
}
