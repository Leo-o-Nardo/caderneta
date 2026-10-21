<?php

namespace App\Enums;

/** O tipo da lista diz quais campos o app mostra e ajuda a IA a escolher a lista certa. */
enum ListKind: string
{
    /** Compras (mercado): quantidade. */
    case Shopping = 'shopping';

    /** Desejos: preço e link. */
    case Wishlist = 'wishlist';

    /** Coisas a fazer: data opcional. */
    case Todo = 'todo';

    /** Criada pelo usuário: todos os campos. */
    case Custom = 'custom';

    public function defaultIcon(): string
    {
        return match ($this) {
            self::Shopping => 'cart',
            self::Wishlist => 'gift',
            self::Todo => 'home',
            self::Custom => 'list',
        };
    }
}
