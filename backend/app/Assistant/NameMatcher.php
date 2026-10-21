<?php

namespace App\Assistant;

use Illuminate\Support\Str;

/**
 * Acha categorias, cartões e listas pelo nome do jeito que as pessoas falam: sem ligar para
 * acento nem maiúscula ("mercado" é "Mercado", "saude" é "Saúde").
 */
final class NameMatcher
{
    public static function normalize(string $text): string
    {
        return Str::of($text)->ascii()->lower()->squish()->toString();
    }

    /**
     * O item cujo nome bate com $name: primeiro o nome igual, depois o que começa com ele
     * ("nubank" acha "Nubank Leo"), por último o que contém.
     *
     * @template T
     *
     * @param  iterable<T>  $items
     * @param  callable(T): string  $nameOf
     * @return T|null
     */
    public static function find(iterable $items, string $name, callable $nameOf): mixed
    {
        $wanted = self::normalize($name);
        $byName = [];

        foreach ($items as $item) {
            $byName[] = [self::normalize($nameOf($item)), $item];
        }

        foreach ([
            fn (string $candidate) => $candidate === $wanted,
            fn (string $candidate) => str_starts_with($candidate, $wanted),
            fn (string $candidate) => str_contains($candidate, $wanted),
        ] as $matches) {
            foreach ($byName as [$candidate, $item]) {
                if ($wanted !== '' && $matches($candidate)) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * Qual dos nomes aparece na frase, como palavra inteira. O mais comprido ganha: em "quanto
     * gastei com contas da casa", a categoria é "Contas da casa", não a lista "Casa".
     *
     * @param  list<string>  $names
     */
    public static function mentioned(array $names, string $text): ?string
    {
        $text = self::normalize($text);
        usort($names, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($names as $name) {
            $pattern = '/\b'.preg_quote(self::normalize($name), '/').'\b/';

            if (preg_match($pattern, $text) === 1) {
                return $name;
            }
        }

        return null;
    }
}
