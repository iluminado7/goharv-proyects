<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Que pasa cuando alguien se pone a si mismo a cargo de un proyecto.
 *
 * La regla del panel es que nadie recibe aviso de lo suyo: ya sabe lo que
 * acaba de hacer. Esto lo deja fijado para los dos caminos, crear y editar,
 * porque la comparacion que lo decide es facil de romper sin darse cuenta.
 */
class SelfAssignNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ponerse_a_cargo_al_crear_no_se_avisa_a_uno_mismo(): void
    {
        Notification::fake();

        $quien = User::factory()->admin()->create();

        $this->actingAs($quien)->post(route('projects.store'), [
            'name'     => 'Mío desde el arranque',
            'status'   => 'nuevo',
            'priority' => 'media',
            'owner_id' => $quien->id,
        ])->assertRedirect();

        Notification::assertNothingSentTo($quien);
    }

    public function test_ponerse_a_cargo_al_editar_no_se_avisa_a_uno_mismo(): void
    {
        Notification::fake();

        $quien   = User::factory()->admin()->create();
        $project = Project::factory()->create(['owner_id' => null]);

        $this->actingAs($quien)->put(route('projects.update', $project), [
            'name'     => $project->name,
            'status'   => $project->status->value,
            'priority' => $project->priority->value,
            'owner_id' => $quien->id,
        ])->assertRedirect();

        Notification::assertNothingSentTo($quien);
    }

    /** Pero a otro sí: es el caso que el aviso existe para cubrir. */
    public function test_poner_a_otro_a_cargo_si_le_avisa(): void
    {
        Notification::fake();

        $quien = User::factory()->admin()->create();
        $otro  = User::factory()->create();

        $this->actingAs($quien)->post(route('projects.store'), [
            'name'     => 'Para otro',
            'status'   => 'nuevo',
            'priority' => 'media',
            'owner_id' => $otro->id,
        ])->assertRedirect();

        Notification::assertSentTo($otro, ProjectEvent::class);
        Notification::assertNothingSentTo($quien);
    }
}
