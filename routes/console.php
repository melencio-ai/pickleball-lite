<?php

use App\Models\Venue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

Artisan::command('venue:create-owner {email} {--name=Venue Owner} {--venue=}', function () {
    $slug = $this->option('venue');
    $venue = $slug ? Venue::where('slug',$slug)->first() : Venue::where('legacy_slot',1)->first();
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


Artisan::command('platform:grant-admin {email}', function () {
    $user = \App\Models\User::whereRaw('LOWER(email) = ?', [strtolower($this->argument('email'))])->first();
    if (!$user) {
        $this->error('No existing account found. Create the verified operator account first.');
        return 1;
    }
    if (!$this->confirm('Grant platform administration to '.$user->email.'?')) return 1;
    $user->forceFill(['platform_admin'=>true])->save();
    $this->info('Platform administration granted.');
    return 0;
})->purpose('Grant explicit platform administration to an existing account');
