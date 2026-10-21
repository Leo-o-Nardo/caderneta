<?php

namespace App\Assistant\Privacy;

/**
 * O cofre de uma pergunta: troca documentos, cartões, telefones, e-mails, chaves Pix e dados
 * bancários por marcadores ("[CPF_1]") antes de o texto ir para a IA, e desfaz a troca no que
 * volta. O mesmo valor ganha sempre o mesmo marcador, mesmo escrito de outro jeito
 * ("529.982.247-25" e "52998224725").
 *
 * Nomes de pessoas não são trocados: a IA precisa deles ("no cartão da Ana").
 */
final class SecureDataVault
{
    // A ordem importa: os formatos mais específicos primeiro, para um CNPJ não virar cartão nem
    // um e-mail com números virar telefone.
    private const EMAIL = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}/';

    // Chave Pix aleatória (UUID).
    private const PIX_KEY = '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i';

    // "agência 1234", "ag. 0001-9": só o número vira marcador.
    private const AGENCY = '/\b(ag(?:[êe]ncia|\.)?)(\s*(?:n[º°o]\.?\s*)?:?\s*)(\d{3,5}(?:-[\dxX])?)\b/iu';

    // "conta 56789-0", "conta corrente 0012345-6", "c/c 1234-5". O dígito depois do hífen é
    // obrigatório: é ele que separa uma conta bancária de "conta de luz" ou "conta 2".
    private const ACCOUNT = '/\b(conta(?:\s+(?:corrente|poupan[çc]a))?|c\/c|cc)(\s*(?:n[º°o]\.?\s*)?:?\s*)(\d{3,12}-[\dxX])(?![\w])/iu';

    private const CNPJ = '/(?<![\d.])\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}(?!\d)/';

    private const CPF = '/(?<![\d.])\d{3}\.\d{3}\.\d{3}-\d{2}(?![\d\-])/';

    // Uma sequência só de dígitos (CPF, CNPJ ou telefone sem pontuação): a conta dos dígitos
    // verificadores ou o formato decide o que ela é.
    private const DIGITS = '/(?<![\d.,\/\-])\+?\d{10,14}(?![\d.,\/\-])/';

    // Cartão: 13 a 19 dígitos em grupos de 4, com ou sem espaço ou hífen. Só vira marcador se
    // passar no teste de Luhn (a conta que valida números de cartão).
    private const CARD = '/(?<![\d.,])(?:\d{4}[ \-]?){3}\d{1,7}(?![\d.,])/';

    // Telefone com DDD ("(11) 98765-4321", "+55 11 98765-4321", "11 98765-4321") ou com hífen
    // ("98765-4321"). Sem DDD nem hífen, oito dígitos soltos podem ser qualquer coisa.
    private const PHONE = '/(?<!\d)(?:\+55\s?)?(?:\(\d{2}\)\s?|\d{2}\s)9?\d{4}[\s\-]?\d{4}(?!\d)|(?<![\d\-])9?\d{4}-\d{4}(?![\d\-])/';

    public const MARKER = '/\[(EMAIL|CHAVE_PIX|AGENCIA|CONTA|CNPJ|CPF|CARTAO|TELEFONE)_(\d+)\]/';

    /** @var array<string, string> marcador → valor como apareceu da primeira vez */
    private array $values = [];

    /** @var array<string, string> tipo|valor normalizado → marcador */
    private array $markers = [];

    /** @var array<string, int> */
    private array $counters = [];

    public function mask(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        $text = preg_replace_callback(self::EMAIL, fn (array $m) => $this->marker('EMAIL', $m[0]), $text);
        $text = preg_replace_callback(self::PIX_KEY, fn (array $m) => $this->marker('CHAVE_PIX', $m[0]), $text);
        $text = preg_replace_callback(self::AGENCY, fn (array $m) => $m[1].$m[2].$this->marker('AGENCIA', $m[3]), $text);
        $text = preg_replace_callback(self::ACCOUNT, fn (array $m) => $m[1].$m[2].$this->marker('CONTA', $m[3]), $text);
        $text = preg_replace_callback(self::CNPJ, fn (array $m) => $this->marker('CNPJ', $m[0]), $text);
        $text = preg_replace_callback(self::CPF, fn (array $m) => $this->marker('CPF', $m[0]), $text);
        $text = preg_replace_callback(self::DIGITS, fn (array $m) => $this->digits($m[0]), $text);
        $text = preg_replace_callback(self::CARD, fn (array $m) => self::luhn(self::onlyDigits($m[0])) ? $this->marker('CARTAO', $m[0]) : $m[0], $text);

        return preg_replace_callback(self::PHONE, fn (array $m) => $this->marker('TELEFONE', $m[0]), $text);
    }

    /** Troca os marcadores de volta. Um marcador que o cofre não conhece fica como está. */
    public function unmask(string $text): string
    {
        if ($this->values === [] || ! str_contains($text, '[')) {
            return $text;
        }

        return preg_replace_callback(self::MARKER, fn (array $m) => $this->values[$m[0]] ?? $m[0], $text);
    }

    /** Esconde todos os textos de uma estrutura (resultados de ferramentas); números e chaves ficam. */
    public function maskArray(array $data): array
    {
        return array_map(fn (mixed $value) => match (true) {
            is_string($value) => $this->mask($value),
            is_array($value) => $this->maskArray($value),
            default => $value,
        }, $data);
    }

    /** Restaura todos os textos de uma estrutura (argumentos de ferramentas). */
    public function unmaskArray(array $data): array
    {
        return array_map(fn (mixed $value) => match (true) {
            is_string($value) => $this->unmask($value),
            is_array($value) => $this->unmaskArray($value),
            default => $value,
        }, $data);
    }

    /** Quantos valores o cofre escondeu nesta pergunta. */
    public function count(): int
    {
        return count($this->values);
    }

    /** O marcador de um valor: o mesmo de antes, se ele já apareceu (escrito de qualquer jeito). */
    private function marker(string $type, string $value): string
    {
        $key = $type.'|'.($type === 'EMAIL' || $type === 'CHAVE_PIX' ? mb_strtolower($value) : self::onlyDigits($value));

        if (! isset($this->markers[$key])) {
            $this->counters[$type] = ($this->counters[$type] ?? 0) + 1;
            $marker = "[{$type}_{$this->counters[$type]}]";
            $this->markers[$key] = $marker;
            $this->values[$marker] = $value;
        }

        return $this->markers[$key];
    }

    /** Uma sequência só de dígitos: CNPJ, CPF ou telefone, nessa ordem. O resto fica como está. */
    private function digits(string $match): string
    {
        $digits = self::onlyDigits($match);

        return match (true) {
            strlen($digits) === 14 && self::validCnpj($digits) => $this->marker('CNPJ', $match),
            strlen($digits) === 11 && self::validCpf($digits) => $this->marker('CPF', $match),
            // Celular com DDD (11 dígitos, o terceiro é 9), fixo com DDD (10) ou com o 55 do país na frente.
            (bool) preg_match('/^(?:55)?[1-9]{2}(?:9\d{8}|[2-5]\d{7})$/', $digits) => $this->marker('TELEFONE', $match),
            default => $match,
        };
    }

    private static function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }

    private static function validCpf(string $digits): bool
    {
        // "111.111.111-11" passa na conta, mas não é um CPF de verdade.
        if (preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        foreach ([9, 10] as $length) {
            $sum = 0;

            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $digits[$i] * ($length + 1 - $i);
            }

            $check = ($sum * 10) % 11 % 10;

            if ($check !== (int) $digits[$length]) {
                return false;
            }
        }

        return true;
    }

    private static function validCnpj(string $digits): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $digits)) {
            return false;
        }

        foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $weights) {
            $sum = 0;

            foreach ($weights as $i => $weight) {
                $sum += (int) $digits[$i] * $weight;
            }

            $check = $sum % 11 < 2 ? 0 : 11 - $sum % 11;

            if ($check !== (int) $digits[count($weights)]) {
                return false;
            }
        }

        return true;
    }

    /** O teste de Luhn: dobra um dígito sim, outro não, da direita para a esquerda; a soma termina em 0. */
    private static function luhn(string $digits): bool
    {
        $length = strlen($digits);

        if ($length < 13 || $length > 19) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $digits[$length - 1 - $i];

            if ($i % 2 === 1) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }
}
