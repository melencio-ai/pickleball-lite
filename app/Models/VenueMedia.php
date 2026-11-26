<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VenueMedia extends Model
{
    public $timestamps = false;

    protected $table = 'venue_media';

    protected $fillable = ['kind', 'mime', 'version', 'content'];
}
