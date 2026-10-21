<?php

namespace App\Enums;

enum HouseholdRole: string
{
    case Owner = 'owner';
    case Member = 'member';
    case Viewer = 'viewer';

    /** O nome do papel como o app mostra (lib/households.ts, ROLES): nos e-mails e nas páginas. */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Dono',
            self::Member => 'Membro',
            self::Viewer => 'Só leitura',
        };
    }

    public function canWrite(): bool
    {
        return $this !== self::Viewer;
    }

    public function canManageMembers(): bool
    {
        return $this === self::Owner;
    }
}
