<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El ojito lo dibuja JavaScript, asi que desde aca solo se puede verificar que
 * el script llegue a las paginas que tienen campos de clave y que los campos
 * sigan siendo type=password en el HTML que sale del servidor.
 */
class PasswordToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_login_trae_el_ojito(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('input[type="password"]', $html);
        $this->assertStringContainsString('Mostrar la clave', $html);
        $this->assertStringContainsString('name="password" type="password"', $html);
    }

    public function test_el_cambio_de_clave_del_perfil_tambien(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('input[type="password"]', $html);

        // Los tres campos del formulario de clave. Se cuentan las etiquetas
        // <input> y no las apariciones del texto, porque el propio script trae
        // el selector 'input[type="password"]' adentro.
        $this->assertSame(3, preg_match_all('/<input[^>]+type="password"/', $html));
    }

    /** Sin JavaScript el campo tiene que seguir siendo un campo de clave normal. */
    public function test_el_servidor_no_manda_la_clave_como_texto_visible(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('name="password" type="text"', escape: false);
    }
}
