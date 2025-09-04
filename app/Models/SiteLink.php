<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteLink extends Model
{
    use HasFactory;


    protected $fillable = [
        'user_id',
        'title',
        'url',
        'duration',
        'is_active',
        'status',
        'notify_email',
        'notify_sms',
        'notify_push',
    ];
}
