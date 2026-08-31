<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectUpdate extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'user_id', 'body', 'status_from', 'status_to'];

    protected function casts(): array
    {
        return [
            'status_from' => ProjectStatus::class,
            'status_to'   => ProjectStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isStatusChange(): bool
    {
        return $this->status_from !== $this->status_to;
    }
}
