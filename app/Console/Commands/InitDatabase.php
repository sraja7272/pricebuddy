<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InitDatabase extends Command
{
    const COMMAND = 'buddy:init-db';

    /**
     * The name and signature of the console command.
     */
    protected $signature = self::COMMAND;

    /**
     * The console command description.
     */
    protected $description = 'Initialize the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            DB::connection()->getPdo();
            $hasTables = Schema::hasTable('migrations');
        } catch (Exception $e) {
            $this->getOutput()->error('Database connection failed, check environment settings');

            return self::FAILURE;
        }

        if ($hasTables) {
            $this->getOutput()->info('Database already initialized');

            $this->components->task('Applying database updates', function () {
                $this->callSilent('migrate', ['--force' => true]);
            });

            // @todo sync stores?
        } else {
            $this->getOutput()->info('Database exists, but not initialized');

            $this->components->task('Setting up the database', fn () => $this
                ->callSilent('migrate:fresh', ['--force' => true])
            );

            // @phpstan-ignore-next-line
            $storeCountry = env('DEFAULT_STORES_COUNTRY', 'all');
            $this->components->task('Creating stores for country: '.$storeCountry, fn () => $this
                ->callSilent(CreateStores::COMMAND, ['country' => $storeCountry])
            );

            // @phpstan-ignore-next-line
            $email = env('APP_USER_EMAIL');
            // @phpstan-ignore-next-line
            $pass = env('APP_USER_PASSWORD');
            if ($email && $pass) {
                $this->line('Creating new user with email: '.$email.' and password: '.$pass);
                $this->components->task('Creating the default user', fn () => $this
                    ->callSilent('make:filament-user', [
                        // @phpstan-ignore-next-line
                        '--name' => env('APP_USER_NAME', 'Admin'),
                        '--email' => $email,
                        '--password' => $pass,
                    ])
                );

                // Grant admin to the bootstrap user (new installs only).
                // Existing installs are covered by the role migration backfill.
                $this->components->task('Granting admin to default user', function () use ($email) {
                    \App\Models\User::where('email', $email)
                        ->first()
                        ?->forceFill(['role' => \App\Enums\Role::Admin])
                        ->save();
                });
            }
        }

        $this->getOutput()->success('Database init complete');

        return self::SUCCESS;
    }
}
