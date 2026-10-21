<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title'      => ucfirst(fake()->words(4, true)),
            'due_date'   => fake()->boolean(60) ? fake()->dateTimeBetween('-1 week', '+1 month') : null,
            'created_by' => User::factory(),
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn () => ['project_id' => $project->id]);
    }

    public function dueOn(?string $fecha): static
    {
        return $this->state(fn () => ['due_date' => $fecha]);
    }

    /** Ya completada, por quien se le pase. */
    public function completed(?User $quien = null): static
    {
        return $this->state(fn () => [
            'completed_at' => now(),
            'completed_by' => $quien?->id,
        ]);
    }
}
