<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin  = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return $this === self::Admin ? 'Responsable' : 'Miembro';
    }
}
