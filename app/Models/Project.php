<?php

namespace App\Models;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'client',
        'status', 'priority', 'owner_id', 'due_date',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status'       => ProjectStatus::class,
            'priority'     => ProjectPriority::class,
            'due_date'     => 'date',
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** Sin espacios de sobra: es texto libre y se filtra por valor exacto. */
    protected function client(): Attribute
    {
        return Attribute::set(function (?string $value) {
            $limpio = trim(preg_replace('/\s+/u', ' ', (string) $value));

            return $limpio === '' ? null : $limpio;
        });
    }

    /** Las empresas ya cargadas, para el filtro y el autocompletado. */
    public static function clientes(): \Illuminate\Support\Collection
    {
        return static::query()
            ->whereNotNull('client')
            ->distinct()
            ->orderBy('client')
            ->pluck('client');
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (blank($project->slug)) {
                $project->slug = static::uniqueSlug($project->name);
            }
        });
    }

    protected static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'proyecto';
        $slug = $base;
        $i    = 1;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class)->latest();
    }

    public function links(): HasMany
    {
        return $this->hasMany(ProjectLink::class)->orderBy('position')->orderBy('id');
    }

    /** El primero de la lista: es el que va en el boton "Abrir" del tablero. */
    public function primaryLink(): ?ProjectLink
    {
        return $this->relationLoaded('links')
            ? $this->links->first()
            : $this->links()->first();
    }

    /**
     * Reemplaza la lista de enlaces por la que llega del formulario.
     * Cada fila es ['label' => ..., 'url' => ...]; las vacias se descartan.
     */
    public function syncLinks(array $rows): void
    {
        $clean = [];

        foreach ($rows as $row) {
            $url = trim((string) ($row['url'] ?? ''));

            if ($url === '') {
                continue;
            }

            $clean[] = [
                'label'    => trim((string) ($row['label'] ?? '')) ?: ProjectLink::labelFromUrl($url),
                'url'      => $url,
                'position' => count($clean),
            ];
        }

        $this->links()->delete();

        if ($clean !== []) {
            $this->links()->createMany($clean);
        }

        $this->unsetRelation('links');
    }

    /**
     * Cambia el estado y deja registro de quien lo hizo.
     * Devuelve false si el estado no cambio, para no ensuciar el historial.
     */
    public function moveTo(ProjectStatus $status, ?User $author = null, ?string $note = null): bool
    {
        if ($this->status === $status && blank($note)) {
            return false;
        }

        $from = $this->status;

        if ($status === ProjectStatus::Inicio && is_null($this->started_at)) {
            $this->started_at = now();
        }

        $this->completed_at = $status === ProjectStatus::Terminado ? now() : null;
        $this->status       = $status;
        $this->save();

        $this->updates()->create([
            'user_id'     => $author?->id,
            'body'        => $note,
            'status_from' => $from->value,
            'status_to'   => $status->value,
        ]);

        return true;
    }

    /**
     * Nota al pie del proyecto, sin moverlo de estado. Se guarda con el estado
     * actual en las dos puntas: asi isStatusChange() da false y el historial lo
     * muestra como comentario y no como movimiento.
     */
    public function comment(User $author, string $body): ProjectUpdate
    {
        return $this->updates()->create([
            'user_id'     => $author->id,
            'body'        => $body,
            'status_from' => $this->status->value,
            'status_to'   => $this->status->value,
        ]);
    }

    /** Orden natural del tablero: primero lo urgente, despues lo mas movido. */
    public function scopeSorted(Builder $query, string $by = 'prioridad'): Builder
    {
        return match ($by) {
            'estado'   => $query->orderBy(static::sequence('status', ProjectStatus::values()))
                                ->orderBy('name'),
            'reciente' => $query->latest('updated_at'),
            'nombre'   => $query->orderBy('name'),
            default    => $query->orderBy(static::sequence('priority', ProjectPriority::values()))
                                ->orderByRaw('case when due_date is null then 1 else 0 end')
                                ->orderBy('due_date')
                                ->orderBy('name'),
        };
    }

    /**
     * CASE WHEN en lugar de FIELD(), que solo existe en MySQL. El orden sale de
     * los enums, asi que agregar un estado nuevo no obliga a tocar esto.
     */
    protected static function sequence(string $column, array $order): Expression
    {
        $pdo = DB::connection()->getPdo();
        $sql = 'case';

        foreach (array_values($order) as $i => $value) {
            $sql .= ' when '.$column.' = '.$pdo->quote($value).' then '.$i;
        }

        return DB::raw($sql.' else '.count($order).' end');
    }

    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['priority'] ?? null, fn ($q, $p) => $q->where('priority', $p))
            ->when($filters['owner'] ?? null, fn ($q, $o) => $q->where('owner_id', $o))
            ->when($filters['client'] ?? null, fn ($q, $c) => $q->where('client', $c))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->search($term));
    }

    /**
     * Donde hay indice fulltext lo usa; en SQLite (los tests) cae al LIKE de
     * siempre. Los terminos de menos de 4 letras tambien van por LIKE: el
     * minimo por defecto de InnoDB es 3 y los descartaria.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term   = trim($term);
        $driver = $query->getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb', 'pgsql'], true) && mb_strlen($term) >= 4) {
            $boolean = collect(preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY))
                ->map(fn ($word) => preg_replace('/[+\-><()~*"@]+/', '', $word))
                ->filter()
                ->map(fn ($word) => '+'.$word.'*')
                ->implode(' ');

            if ($boolean !== '') {
                return $query->whereFullText(['name', 'description', 'client'], $boolean, ['mode' => 'boolean']);
            }
        }

        return $query->where(function (Builder $sub) use ($term) {
            $sub->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('client', 'like', "%{$term}%");
        });
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->status !== ProjectStatus::Terminado
            && $this->due_date->isPast();
    }
}
