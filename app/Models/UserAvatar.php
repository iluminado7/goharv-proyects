<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAvatar extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = ['user_id', 'mime', 'image'];

    /** Nunca en un JSON ni en un dump: son cientos de KB de binario. */
    protected $hidden = ['image'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
