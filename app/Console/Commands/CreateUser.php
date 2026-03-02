<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = text(
            label: 'Name',
            required: true,
        );

        $surname = text(
            label: 'Surname',
            required: true,
        );

        $email = text(
            label: 'Email',
            required: true,
            validate: ['email' => 'required|email|unique:users,email'],
        );

        $password = password(
            label: 'Password',
            required: true,
            validate: ['password' => 'required|min:8'],
        );

        User::create([
            'name' => "{$name} {$surname}",
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $this->info("User {$email} created successfully.");

        return self::SUCCESS;
    }
}
