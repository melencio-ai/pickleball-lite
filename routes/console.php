<?php

use App\Models\Venue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

Artisan::command('venue:create-owner {email} {--name=Venue Owner}', function () {
    $venue = Venue::query()->orderBy('id')->first();
    if (! $venue) {
        $this->error('Run php artisan db:seed to initialize the venue first.');

        return 1;
    }

    $email = strtolower($this->argument('email'));
    $name = $this->option('name');
    $password = $this->secret('Owner password (at least 12 characters)');
    $validator = Validator::make(compact('email', 'name', 'password'), [
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'name' => ['required', 'string', 'max:100'],
        'password' => ['required', 'string', 'min:12', 'max:72'],
    ]);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }

    $venue->users()->create($validator->validated() + ['role' => 'owner']);
    $this->info('Venue owner created.');

    return 0;
})->purpose('Create an owner account for the venue');
