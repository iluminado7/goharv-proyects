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

    /**
     * users.avatar_updated_at acompana a esta tabla. Se mantiene desde aca y
     * no desde el controlador para que no dependa de que alguien se acuerde.
     */
    protected static function booted(): void
    {
        static::saved(fn (self $avatar) => User::whereKey($avatar->user_id)
            ->update(['avatar_updated_at' => $avatar->updated_at]));

        static::deleted(fn (self $avatar) => User::whereKey($avatar->user_id)
            ->update(['avatar_updated_at' => null]));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
