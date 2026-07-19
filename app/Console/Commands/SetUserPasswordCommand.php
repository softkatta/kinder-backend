<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUserPasswordCommand extends Command
{
    protected $signature = 'user:set-password
                            {email : User email}
                            {password : New password (min 8 characters)}';

    protected $description = 'Set a user password (Hostinger-safe; no tinker/shell_exec)';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $password = (string) $this->argument('password');

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->error("No user found for email: {$email}");
            $this->line('Existing users:');
            User::query()->orderBy('id')->get(['id', 'email', 'name'])->each(
                fn (User $u) => $this->line("  #{$u->id}  {$u->email}  ({$u->name})")
            );

            return self::FAILURE;
        }

        $user->password = $password;
        $user->save();

        $this->info("Password updated for {$user->email} (id {$user->id}).");

        return self::SUCCESS;
    }
}
