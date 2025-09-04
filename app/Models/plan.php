<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'price',
        'duration',
        'is_active',
        'whatsapp_notifications',
        'email_notifications',
        'sms_notifications',
        'push_notifications',
        'priority_support',
    ];
}
