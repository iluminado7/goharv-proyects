<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_respuestas_traen_las_cabeceras_de_defensa(): void
    {
        $r = $this->get('/login')->assertOk();

        $r->assertHeader('X-Frame-Options', 'DENY');
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $r->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $r->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    /** Sin nonce la CSP bloquearia los dos scripts del panel. */
    public function test_los_scripts_propios_llevan_el_nonce_de_la_csp(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        preg_match("/'nonce-([A-Za-z0-9]+)'/", $this->get('/login')->headers->get('Content-Security-Policy'), $m);

        $this->assertStringContainsString('<script nonce="', $html);
        $this->assertStringNotContainsString("'unsafe-inline'", explode('style-src', $this->get('/login')->headers->get('Content-Security-Policy'))[0]);
    }

    public function test_el_nonce_cambia_en_cada_visita(): void
    {
        $uno = $this->get('/login')->headers->get('Content-Security-Policy');
        $dos = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertNotSame($uno, $dos);
    }

    /** HSTS sobre HTTP no sirve de nada y rompe el acceso local. */
    public function test_hsts_solo_cuando_ya_hay_https(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('/login', ['X-Forwarded-Proto' => 'https'])
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_una_cuenta_de_baja_no_se_delata_en_el_login(): void
    {
        $baja = User::factory()->inactive()->create(['password' => 'la-clave-correcta']);

        $this->post('/login', ['email' => $baja->email, 'password' => 'la-clave-correcta'])
            ->assertSessionHasErrors(['email' => 'Esos datos no coinciden con ninguna cuenta.']);

        // Idéntico a probar una clave equivocada: no hay forma de distinguirlos.
        $this->post('/login', ['email' => $baja->email, 'password' => 'cualquier-otra'])
            ->assertSessionHasErrors(['email' => 'Esos datos no coinciden con ninguna cuenta.']);
    }

    public function test_el_cambio_de_clave_tiene_limite_de_intentos(): void
    {
        $user = User::factory()->create(['password' => 'la-de-antes']);

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->put(route('profile.password'), [
                'current_password'      => 'adivinando-'.$i,
                'password'              => 'la-nueva-clave',
                'password_confirmation' => 'la-nueva-clave',
            ]);
        }

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password'      => 'la-de-antes',
                'password'              => 'la-nueva-clave',
                'password_confirmation' => 'la-nueva-clave',
            ])
            ->assertStatus(429);
    }

    public function test_los_comentarios_tambien_tienen_freno(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->create();

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($user)->post(route('projects.comment', $project), ['body' => 'spam '.$i]);
        }

        $this->actingAs($user)
            ->post(route('projects.comment', $project), ['body' => 'uno mas'])
            ->assertStatus(429);
    }

    public function test_un_responsable_no_puede_sacarse_a_si_mismo(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create(); // Otro activo: no es el "ultimo responsable".

        $this->actingAs($admin)
            ->patch(route('members.update', $admin), ['role' => 'member', 'is_active' => 1])
            ->assertSessionHasErrors('role');

        $this->actingAs($admin)
            ->patch(route('members.update', $admin), ['role' => 'admin', 'is_active' => 0])
            ->assertSessionHasErrors('role');

        $admin->refresh();

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->is_active);
    }

    public function test_la_clave_inicial_no_queda_a_la_vista(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('members.index'))
            ->assertOk()
            ->assertSee('name="password" type="password"', escape: false)
            ->assertDontSee('name="password" type="text"', escape: false);
    }
}
