<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Una tarea dentro de un proyecto, a cargo de una o varias personas.
 *
 * Los dos estados —pendiente y completada— salen de completed_at: vacío es
 * pendiente, con fecha es completada. No hay enum porque no hay nada que
 * enumerar; el día que aparezca un tercer estado, ahí sí.
 */
class Task extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'title', 'due_date', 'created_by'];

    protected function casts(): array
    {
        return [
            'due_date'     => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** A quiénes va dirigida. */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /** Venció si tenía fecha, ya pasó, y todavía nadie la completó. */
    public function isOverdue(): bool
    {
        return $this->due_date
            && ! $this->isCompleted()
            && $this->due_date->isPast();
    }

    /**
     * Marca o desmarca, dejando quién fue. Devuelve false si ya estaba así,
     * para que quien llame no registre un movimiento que no ocurrió.
     */
    public function toggle(bool $completada, ?User $quien = null): bool
    {
        if ($completada === $this->isCompleted()) {
            return false;
        }

        $this->forceFill([
            'completed_at' => $completada ? now() : null,
            'completed_by' => $completada ? $quien?->id : null,
        ])->save();

        return true;
    }

    /**
     * Reemplaza los asignados. Solo quienes participan del proyecto: una tarea
     * dirigida a alguien que no está en el proyecto no la va a ver nadie.
     */
    public function assignTo(array $userIds): void
    {
        $delProyecto = $this->project->interesados()->pluck('id');

        $this->assignees()->sync(
            collect($userIds)->map(fn ($id) => (int) $id)->intersect($delProyecto)->all()
        );
    }

    /** Quienes participan del proyecto y pueden recibir esta tarea. */
    public function candidatos(): Collection
    {
        return $this->project->interesados();
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function scopeCompletadas(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeVencidas(Builder $query): Builder
    {
        return $query->pendientes()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now());
    }

    /** Pendientes primero, y dentro de cada grupo lo que vence antes. */
    public function scopeSorted(Builder $query): Builder
    {
        return $query
            ->orderByRaw('case when completed_at is null then 0 else 1 end')
            ->orderByRaw('case when due_date is null then 1 else 0 end')
            ->orderBy('due_date')
            ->orderBy('id');
    }

    public function scopeFor(Builder $query, User $user): Builder
    {
        return $query->whereHas('assignees', fn ($q) => $q->whereKey($user->id));
    }
}
