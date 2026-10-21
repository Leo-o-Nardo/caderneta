<?php

namespace App\Support;

final class Money
{
    /**
     * 123456 → "R$ 1.234,56". As ferramentas do assistente já mandam o valor escrito assim, para a
     * IA só copiar e nunca fazer conta nem arredondar por conta própria.
     */
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';

        return $sign.'R$ '.number_format(abs($cents) / 100, 2, ',', '.');
    }

    /**
     * Divide um valor em centavos em parcelas inteiras cuja soma é exatamente o total.
     * Os centavos que sobram da divisão vão para as primeiras parcelas: 100,00 em 3x = 33,34 + 33,33 + 33,33.
     *
     * @return list<int>
     */
    public static function split(int $totalCents, int $parts): array
    {
        $base = intdiv($totalCents, $parts);
        $remainder = $totalCents % $parts;

        return array_map(
            fn (int $index) => $base + ($index < $remainder ? 1 : 0),
            range(0, $parts - 1),
        );
    }
}
