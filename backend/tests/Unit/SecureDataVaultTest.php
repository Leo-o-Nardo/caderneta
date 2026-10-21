<?php

use App\Assistant\Privacy\DeltaUnmasker;
use App\Assistant\Privacy\SecureDataVault;

it('hides each kind of sensitive data', function (string $text, string $masked) {
    expect((new SecureDataVault)->mask($text))->toBe($masked);
})->with([
    'formatted CPF' => ['meu cpf é 529.982.247-25', 'meu cpf é [CPF_1]'],
    'CPF only digits, with valid check digits' => ['cpf 52998224725', 'cpf [CPF_1]'],
    'formatted CNPJ' => ['cnpj 11.222.333/0001-81', 'cnpj [CNPJ_1]'],
    'CNPJ only digits' => ['cnpj 11222333000181', 'cnpj [CNPJ_1]'],
    'card with spaces' => ['cartão 4111 1111 1111 1111', 'cartão [CARTAO_1]'],
    'card only digits' => ['número 5555555555554444', 'número [CARTAO_1]'],
    'mobile with area code' => ['liga no (11) 98765-4321', 'liga no [TELEFONE_1]'],
    'mobile only digits' => ['pix para 11987654321', 'pix para [TELEFONE_1]'],
    'mobile with country code' => ['whats +55 11 98765-4321', 'whats [TELEFONE_1]'],
    'mobile without area code' => ['liga 98765-4321', 'liga [TELEFONE_1]'],
    'email' => ['manda para leo.souza@example.com.br', 'manda para [EMAIL_1]'],
    'random Pix key' => ['chave 123e4567-e89b-12d3-a456-426614174000', 'chave [CHAVE_PIX_1]'],
    'bank branch and account' => ['agência 1234 conta 56789-0', 'agência [AGENCIA_1] conta [CONTA_1]'],
    'checking account' => ['conta corrente 0012345-6', 'conta corrente [CONTA_1]'],
]);

it('leaves amounts, dates and ordinary numbers alone', function (string $text) {
    expect((new SecureDataVault)->mask($text))->toBe($text);
})->with([
    'money' => ['gastei R$ 1.234,56 no mercado'],
    'day and time' => ['me lembra dia 20/10 às 10h'],
    'ISO dates' => ['de 2026-10-01 a 2026-10-09'],
    'a utility bill' => ['conta de luz de 182,40'],
    'the end of a card' => ['cartão final 1234'],
    'counts' => ['11 itens e 30 reais'],
    '11 digits that are neither CPF nor mobile' => ['protocolo 12345678900'],
    'card number that fails Luhn' => ['número 4111 1111 1111 1112'],
]);

it('gives the same marker to the same value, even written another way', function () {
    $vault = new SecureDataVault;

    expect($vault->mask('cpf 529.982.247-25'))->toBe('cpf [CPF_1]')
        ->and($vault->mask('de novo: 52998224725'))->toBe('de novo: [CPF_1]')
        ->and($vault->mask('outro: 111.444.777-35'))->toBe('outro: [CPF_2]')
        ->and($vault->count())->toBe(2);
});

it('restores the markers, also inside nested results', function () {
    $vault = new SecureDataVault;
    $masked = $vault->maskArray(['summary' => 'Pix para 11987654321', 'items' => [['text' => 'leo@example.com']], 'total_cents' => 5000]);

    expect($masked)->toBe(['summary' => 'Pix para [TELEFONE_1]', 'items' => [['text' => '[EMAIL_1]']], 'total_cents' => 5000])
        ->and($vault->unmaskArray($masked))->toBe(['summary' => 'Pix para 11987654321', 'items' => [['text' => 'leo@example.com']], 'total_cents' => 5000])
        // Marcador que o cofre não conhece (inventado pela IA) fica como está.
        ->and($vault->unmask('[CPF_9] e [TELEFONE_1]'))->toBe('[CPF_9] e 11987654321');
});

it('restores a marker split between two pieces of the stream', function () {
    $vault = new SecureDataVault;
    $vault->mask('cpf 529.982.247-25');
    $pieces = [];
    $stream = new DeltaUnmasker($vault, function (string $piece) use (&$pieces) {
        $pieces[] = $piece;
    });

    $stream->push('Seu CPF é [CP');
    $stream->push('F_1], certo? Na lista [comp');
    $stream->push('ras] também.');
    $stream->flush();

    expect(implode('', $pieces))->toBe('Seu CPF é 529.982.247-25, certo? Na lista [compras] também.')
        // A tela nunca recebe um marcador nem um pedaço dele.
        ->and(array_filter($pieces, fn (string $piece) => preg_match('/\[[A-Z]/', $piece) === 1))->toBe([]);
});
