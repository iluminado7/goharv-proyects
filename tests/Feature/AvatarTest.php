<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Support\AvatarImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * La foto se guarda en la base y no en disco: en Laravel Cloud el filesystem
 * se borra en cada deploy. Quien no sube ninguna queda con sus iniciales.
 */
class AvatarTest extends TestCase
{
    use RefreshDatabase;

    private function imagen(int $ancho = 900, int $alto = 500): UploadedFile
    {
        return UploadedFile::fake()->image('yo.jpg', $ancho, $alto);
    }

    public function test_se_sube_una_foto_y_queda_cuadrada_y_liviana(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.avatar.update'), ['avatar' => $this->imagen()])
            ->assertRedirect(route('profile.edit'));

        $avatar = $user->fresh()->avatar;

        $this->assertNotNull($avatar);
        $this->assertSame('image/jpeg', $avatar->mime);

        [$ancho, $alto] = getimagesizefromstring($avatar->image);

        $this->assertSame(AvatarImage::LADO, $ancho);
        $this->assertSame(AvatarImage::LADO, $alto);
        $this->assertLessThan(120_000, strlen($avatar->image), 'La foto tendría que quedar bajo 120 KB');
    }

    public function test_subir_de_nuevo_reemplaza_y_no_acumula(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => $this->imagen()]);
        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => $this->imagen(400, 400)]);

        $this->assertSame(1, $user->fresh()->avatar()->count());
        $this->assertDatabaseCount('user_avatars', 1);
    }

    public function test_la_foto_se_sirve_y_el_navegador_la_puede_cachear(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => $this->imagen()]);

        $r = $this->actingAs($user)->get(route('users.avatar', $user))->assertOk();

        $r->assertHeader('Content-Type', 'image/jpeg');
        // Privada: no la tiene que guardar el CDN, son caras del equipo.
        $this->assertStringContainsString('private', $r->headers->get('Cache-Control'));

        $etag = $r->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->actingAs($user)
            ->get(route('users.avatar', $user), ['If-None-Match' => $etag])
            ->assertStatus(304);
    }

    public function test_quien_no_tiene_foto_da_404_y_muestra_iniciales(): void
    {
        $user = User::factory()->create(['name' => 'Franco Romero']);

        $this->actingAs($user)->get(route('users.avatar', $user))->assertNotFound();

        $this->assertSame('FR', $user->initials());

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('avatar-txt', escape: false)
            ->assertSee('>FR</span>', escape: false);
    }

    public function test_se_puede_quitar_la_foto(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => $this->imagen()]);

        $this->actingAs($user)
            ->delete(route('profile.avatar.destroy'))
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseCount('user_avatars', 0);
        $this->assertFalse($user->fresh()->hasAvatar());
    }

    public function test_un_archivo_que_no_es_imagen_se_rechaza(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.avatar.update'), [
                'avatar' => UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('avatar');

        $this->assertDatabaseCount('user_avatars', 0);
    }

    public function test_sin_sesion_no_se_ven_las_caras_del_equipo(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => $this->imagen()]);

        $this->flushSession();
        $this->app['auth']->logout();

        $this->get(route('users.avatar', $user))->assertRedirect('/login');
    }

    public function test_la_cara_aparece_en_el_historial_y_en_el_equipo(): void
    {
        $admin   = User::factory()->admin()->create(['name' => 'Ana Gómez']);
        $project = Project::factory()->create();
        $project->comment($admin, 'Una nota.');

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('>AG</span>', escape: false);

        $this->actingAs($admin)
            ->get(route('members.index'))
            ->assertOk()
            ->assertSee('>AG</span>', escape: false);
    }

    /** Borrar la cuenta se lleva la foto: la clave foránea está en cascada. */
    public function test_la_foto_no_queda_huerfana(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => $this->imagen()]);

        $user->forceDelete();

        $this->assertDatabaseCount('user_avatars', 0);
    }
}
