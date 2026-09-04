<?php

namespace Database\Factories;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'name'        => ucfirst(fake()->unique()->words(3, true)),
            'description' => fake()->sentence(12),
            'client'      => fake()->boolean(70) ? fake()->company() : null,
            'status'      => fake()->randomElement(ProjectStatus::cases()),
            'priority'    => fake()->randomElement(ProjectPriority::cases()),
            'owner_id'    => User::factory(),
            'due_date'    => fake()->boolean(60) ? fake()->dateTimeBetween('-1 month', '+3 months') : null,
        ];
    }

    public function status(ProjectStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function priority(ProjectPriority $priority): static
    {
        return $this->state(fn () => ['priority' => $priority]);
    }

    public function forClient(?string $client): static
    {
        return $this->state(fn () => ['client' => $client]);
    }

    public function ownedBy(User $user): static
    {
        return $this->state(fn () => ['owner_id' => $user->id]);
    }
}
