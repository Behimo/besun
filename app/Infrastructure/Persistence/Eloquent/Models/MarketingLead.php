<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingLead extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'message',
        'source',
    ];
}
