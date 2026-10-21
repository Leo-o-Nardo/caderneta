<?php

namespace App\Services;

use App\Enums\CategoryKind;
use App\Models\Household;

/**
 * As categorias com que toda casa nova começa. O ícone é uma chave que o app traduz para o desenho;
 * a cor é um dos tons de pigmento do guia de design (parte 02).
 */
final class DefaultCategories
{
    private const EXPENSES = [
        ['Mercado', 'cart', '#4F7F4F'],
        ['Alimentação', 'utensils', '#B8902F'],
        ['Delivery', 'bike', '#B9553A'],
        ['Transporte', 'car', '#4A6A9A'],
        ['Moradia', 'home', '#7E5490'],
        ['Contas da casa', 'bolt', '#7F8A3A'],
        ['Saúde', 'heart', '#B5677E'],
        ['Lazer', 'ticket', '#357A8F'],
        ['Compras', 'bag', '#C27A3A'],
        ['Assinaturas', 'repeat', '#5A5A9A'],
        ['Educação', 'book', '#3A8577'],
        ['Pets', 'paw', '#9A4A72'],
        ['Outros', 'dots', '#7D786D'],
    ];

    private const INCOMES = [
        ['Salário', 'wallet', '#4F7F4F'],
        ['Renda extra', 'trending-up', '#4A6A9A'],
        ['Outros', 'dots', '#7D786D'],
    ];

    public static function createFor(Household $household): void
    {
        $rows = [
            ...self::rows(CategoryKind::Expense, self::EXPENSES),
            ...self::rows(CategoryKind::Income, self::INCOMES),
        ];

        $household->categories()->createMany($rows);
    }

    private static function rows(CategoryKind $kind, array $list): array
    {
        return array_map(fn (array $item) => [
            'kind' => $kind,
            'name' => $item[0],
            'icon' => $item[1],
            'color' => $item[2],
        ], $list);
    }
}
