<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'  => 'hashed',
            'is_active'         => 'boolean',
            'avatar_updated_at' => 'datetime',
            'role'      => UserRole::class,
        ];
    }

    /**
     * La foto vive en su propia tabla: una relacion y no una columna, para que
     * el binario no viaje en cada listado de usuarios.
     */
    public function avatar(): HasOne
    {
        return $this->hasOne(UserAvatar::class);
    }

    public function hasAvatar(): bool
    {
        return $this->avatar_updated_at !== null;
    }

    /**
     * La URL lleva la fecha de la foto: al cambiarla cambia la direccion, y
     * entonces el navegador la pide de nuevo en vez de mostrar la anterior.
     */
    public function avatarUrl(): ?string
    {
        if (! $this->hasAvatar()) {
            return null;
        }

        return route('users.avatar', [$this, 'v' => $this->avatar_updated_at->getTimestamp()]);
    }

    /** Hasta dos letras; con eso alcanza para reconocer a alguien. */
    public function initials(): string
    {
        $palabras = preg_split('/\s+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($palabras === []) {
            return '?';
        }

        $primera = Str::upper(Str::substr($palabras[0], 0, 1));

        if (count($palabras) === 1) {
            return $primera;
        }

        return $primera.Str::upper(Str::substr(end($palabras), 0, 1));
    }

    /**
     * Color estable sacado del nombre: la misma persona siempre tiene el mismo,
     * y no hace falta guardarlo ni elegirlo. Son tonos de la paleta del panel.
     */
    public function avatarColor(): string
    {
        $tonos = ['#6BA5E7', '#E0A33E', '#4FA97C', '#E05C4B', '#A585D8', '#49A8A0', '#C9A227'];

        return $tonos[crc32(Str::lower($this->name ?: '?')) % count($tonos)];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** Proyectos donde figura como responsable. */
    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    /** Proyectos donde colabora sin ser el responsable. */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }
}
