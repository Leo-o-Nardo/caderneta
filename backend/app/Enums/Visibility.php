<?php

namespace App\Enums;

enum Visibility: string
{
    /** Todos da casa veem. */
    case Household = 'household';

    /** Só quem criou vê. */
    case Private = 'private';
}
