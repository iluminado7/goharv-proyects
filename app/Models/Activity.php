<?php

namespace App\Models;

use App\Enums\ActivityAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;

class Activity extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';

    /** Solo created_at: una bitacora se escribe, no se edita. */
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'project_id', 'subject', 'detail', 'ip'];

    protected function casts(): array
    {
        return [
            'action'     => ActivityAction::class,
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Unico punto de entrada para anotar algo.
     *
     * Nunca puede voltear la accion que la origina: si la bitacora falla, el
     * proyecto igual se guarda y el usuario igual entra. Un log roto es un
     * problema, pero menor que un panel que deja de funcionar por el log.
     */
    public static function anotar(
        ActivityAction $action,
        ?User $user = null,
        ?Project $project = null,
        ?string $subject = null,
        ?string $detail = null,
    ): void {
        try {
            static::create([
                'user_id'    => $user?->id,
                'action'     => $action->value,
                'project_id' => $project?->exists ? $project->getKey() : null,
                'subject'    => $subject ?? $project?->name,
                'detail'     => $detail,
                'ip'         => Request::ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['action'] ?? null, fn ($q, $a) => $q->where('action', $a))
            ->when($filters['user'] ?? null, fn ($q, $u) => $q->where('user_id', $u))
            ->when(($filters['tipo'] ?? null) === 'acceso',
                fn ($q) => $q->whereIn('action', array_column(ActivityAction::acceso(), 'value')))
            ->when(($filters['tipo'] ?? null) === 'fallos',
                fn ($q) => $q->whereIn('action', [
                    ActivityAction::ClaveIncorrecta->value,
                    ActivityAction::CorreoInexistente->value,
                    ActivityAction::CuentaDeBaja->value,
                ]))
            ->when(($filters['tipo'] ?? null) === 'proyectos',
                fn ($q) => $q->whereIn('action', array_column(ActivityAction::proyectos(), 'value')));
    }

    /** Quien figura: el usuario si existe, o el correo que se intento. */
    public function quien(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        return $this->action->esFallo() && $this->subject
            ? $this->subject
            : 'Alguien';
    }
}
