<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password as pedirClave;
use function Laravel\Prompts\text;

/**
 * Crea el primer responsable del panel.
 *
 * Reemplaza al admin que sembraba el seeder con una contraseña escrita en el
 * repositorio. Acá la clave se pide por teclado, no se escribe en pantalla, no
 * queda en el historial de la terminal y no toca ningún archivo.
 *
 * Es para arrancar un panel vacío. Con el panel andando, las cuentas se crean
 * desde Equipo, que además manda el correo de bienvenida y obliga a cambiar la
 * clave al entrar.
 */
class CrearResponsable extends Command
{
    protected $signature = 'goharv:responsable';

    protected $description = 'Crea el primer responsable del panel, pidiendo la clave por teclado';

    public function handle(): int
    {
        // Sin terminal no hay forma de pedir la clave, y aceptarla por
        // parámetro la dejaría en el historial y en los logs del servidor.
        if (! $this->input->isInteractive()) {
            $this->error('Este comando necesita una terminal: la clave se escribe a mano.');

            return self::FAILURE;
        }

        if ($yaHay = User::where('role', UserRole::Admin)->count()) {
            $this->warn("Ya hay {$yaHay} responsable(s) en el panel.");

            if (! $this->confirm('¿Crear otro igual?', false)) {
                return self::SUCCESS;
            }
        }

        $nombre = text('Nombre', required: true);
        $correo = text('Correo', required: true);
        $clave  = pedirClave('Clave', required: true);

        if ($clave !== pedirClave('Repetila', required: true)) {
            $this->error('Las claves no coinciden. No se creó nada.');

            return self::FAILURE;
        }

        $validacion = Validator::make(
            ['name' => $nombre, 'email' => $correo, 'password' => $clave],
            [
                'name'     => ['required', 'string', 'max:80'],
                'email'    => ['required', 'email', 'max:120', 'unique:users,email'],
                'password' => ['required', 'string', Password::defaults()],
            ],
        );

        if ($validacion->fails()) {
            foreach ($validacion->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name'      => $nombre,
            'email'     => $correo,
            'password'  => $clave,
            'role'      => UserRole::Admin,
            'is_active' => true,
            // La eligió esta persona: no hay nada que forzar a cambiar.
            'must_change_password' => false,
        ]);

        $this->info("Listo. {$user->name} ya puede entrar con {$user->email}.");

        return self::SUCCESS;
    }
}
