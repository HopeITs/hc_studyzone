<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialLink extends Model
{
    protected $fillable = ['city', 'facebook', 'instagram', 'whatsapp', 'telegram', 'website'];

    public function city()
    {
        return $this->belongsTo(City::class, 'city', 'name');
    }
}
