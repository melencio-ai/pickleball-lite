<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('slug', 100)->nullable()->unique();
            $table->unsignedTinyInteger('legacy_slot')->nullable()->unique();
            $table->string('status', 20)->default('active');
            $table->boolean('published')->default(true);
        });
        Schema::table('users', fn (Blueprint $table) => $table->boolean('platform_admin')->default(false));
        Schema::create('venue_memberships', function (Blueprint $table) {
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();
            $table->primary(['venue_id','user_id']);
            $table->index(['user_id','venue_id']);
        });

        $first = true;
        DB::table('venues')->orderBy('id')->chunkById(100, function ($venues) use (&$first) {
            foreach ($venues as $venue) {
                $base = substr(Str::slug($venue->name), 0, 80) ?: 'venue';
                $slug = $base;
                if (in_array($slug, ['up','assets','demo-gallery','branding','book','open-play','staff','login','admin','venues'], true)
                    || DB::table('venues')->where('slug',$slug)->exists()) {
                    $slug = $base.'-'.$venue->id;
                    while (DB::table('venues')->where('slug',$slug)->exists()) $slug .= '-v';
                }
                DB::table('venues')->where('id',$venue->id)->update(['slug'=>$slug,'legacy_slot'=>$first ? 1 : null]);
                $first = false;
            }
        });
        DB::table('users')->whereNotNull('venue_id')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                if (in_array($user->role, ['owner','staff','manager'], true)) {
                    DB::table('venue_memberships')->insertOrIgnore([
                        'venue_id'=>$user->venue_id,'user_id'=>$user->id,'role'=>$user->role,
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Tenant migration is forward-only. Restore a verified backup instead of deleting memberships.');
    }
};
