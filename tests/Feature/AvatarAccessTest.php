<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Quién puede ver la foto de quién.
 *
 * La ruta servía la foto de cualquiera a cualquiera con sesión. Entre
 * nosotros está bien: las caras aparecen en tareas, notas y en Equipo. Pero
 * al profesional externo le bloqueamos Equipo justamente para que no vea
 * quiénes somos, y recorriendo /equipo/1/foto, /equipo/2/foto… obtenía la
 * misma lista por la puerta de atrás.
 */
class AvatarAccessTest extends TestCase
{
    use RefreshDatabase;

    private function conFoto(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);

        $this->actingAs($user)->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('yo.jpg', 400, 400),
        ]);

        return $user->fresh();
    }

    // --- El equipo se ve entre sí ------------------------------------------

    public function test_un_socio_ve_la_foto_de_cualquiera(): void
    {
        $socio = User::factory()->create();
        $otro  = $this->conFoto();

        $this->actingAs($socio)->get(route('users.avatar', $otro))->assertOk();
    }

    public function test_un_responsable_tambien(): void
    {
        $admin = User::factory()->admin()->create();
        $otro  = $this->conFoto();

        $this->actingAs($admin)->get(route('users.avatar', $otro))->assertOk();
    }

    // --- El externo, solo con quien comparte proyecto ----------------------

    public function test_el_externo_no_ve_la_foto_de_alguien_ajeno(): void
    {
        $externo = User::factory()->external()->create();
        $ajeno   = $this->conFoto();

        // 404 y no 403: un 403 confirmaría que ese usuario existe.
        $this->actingAs($externo)->get(route('users.avatar', $ajeno))->assertNotFound();
    }

    public function test_el_externo_si_ve_la_del_responsable_de_su_proyecto(): void
    {
        $externo = User::factory()->external()->create();
        $duenio  = $this->conFoto();

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach($externo);

        $this->actingAs($externo)->get(route('users.avatar', $duenio))->assertOk();
    }

    public function test_el_externo_ve_la_de_un_colaborador_de_su_proyecto(): void
    {
        $externo = User::factory()->external()->create();
        $colega  = $this->conFoto();

        $project = Project::factory()->ownedBy($externo)->create();
        $project->collaborators()->attach($colega);

        $this->actingAs($externo)->get(route('users.avatar', $colega))->assertOk();
    }

    public function test_el_externo_siempre_ve_la_suya(): void
    {
        $externo = $this->conFoto(['role' => 'external']);

        $this->actingAs($externo)->get(route('users.avatar', $externo))->assertOk();
    }

    /** Un proyecto archivado sigue a la vista en Archivados, y la foto también. */
    public function test_tambien_en_un_proyecto_archivado(): void
    {
        $externo = User::factory()->external()->create();
        $duenio  = $this->conFoto();

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach($externo);
        $project->delete();

        $this->actingAs($externo)->get(route('users.avatar', $duenio))->assertOk();
    }

    /** Recorrer IDs no puede darle la lista de quiénes somos. */
    public function test_el_externo_no_puede_enumerar_al_equipo(): void
    {
        $externo = User::factory()->external()->create();

        $equipo = collect(range(1, 4))->map(fn () => $this->conFoto());

        foreach ($equipo as $alguien) {
            $this->actingAs($externo)
                ->get(route('users.avatar', $alguien))
                ->assertNotFound();
        }
    }

    // --- Sin sesión --------------------------------------------------------

    public function test_sin_sesion_no_hay_fotos(): void
    {
        $alguien = $this->conFoto();

        // conFoto() sube la imagen con actingAs, y eso deja la sesión puesta
        // para el resto del test. Hay que soltarla para probar el caso.
        auth()->logout();

        $this->get(route('users.avatar', $alguien))->assertRedirect(route('login'));
    }
}
