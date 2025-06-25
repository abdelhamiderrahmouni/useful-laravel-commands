<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetUserPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:reset-password 
                            {login : The user login (email by default)}
                            {password=password : The new password (default: password)}
                            {--login=email : The login column to search by (default: email)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset a user password by providing their login credentials';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $loginValue = $this->argument('login');
        $newPassword = $this->argument('password');
        $loginColumn = $this->option('login');

        // Validate the login column exists in the users table
        if (!$this->isValidLoginColumn($loginColumn)) {
            $this->error("Invalid login column: {$loginColumn}");
            $this->info('Available columns: email, username, phone, id');
            return Command::FAILURE;
        }

        // Find the user
        $user = User::where($loginColumn, $loginValue)->first();

        if (!$user) {
            $this->error("User not found with {$loginColumn}: {$loginValue}");
            return Command::FAILURE;
        }

        // Confirm the action
        if (!$this->confirmReset($user, $loginColumn, $newPassword)) {
            $this->info('Password reset cancelled.');
            return Command::SUCCESS;
        }

        // Reset the password
        try {
            $user->password = Hash::make($newPassword);
            $user->save();

            $this->info("✅ Password successfully reset for user: {$user->name} ({$user->email})");
            $this->line("🔑 New password: {$newPassword}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to reset password: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Validate if the login column exists and is allowed
     */
    private function isValidLoginColumn(string $column): bool
    {
        $allowedColumns = ['email', 'username', 'phone', 'id'];

        if (!in_array($column, $allowedColumns)) {
            return false;
        }

        // Check if column exists in users table
        try {
            return \Schema::hasColumn('users', $column);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Confirm the password reset action
     */
    private function confirmReset(User $user, string $loginColumn, string $password): bool
    {
        $this->newLine();
        $this->line('📋 <comment>Password Reset Summary:</comment>');
        $this->line("👤 User: {$user->name}");
        $this->line("📧 Email: {$user->email}");
        $this->line("🔍 Found by: {$loginColumn}");
        $this->line("🔑 New Password: {$password}");
        $this->newLine();

        return $this->confirm('Are you sure you want to reset this user\'s password?', false);
    }
}