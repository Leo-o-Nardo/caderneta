<?php

namespace App\Assistant\Privacy;

use Closure;

/**
 * Restaura os marcadores no texto que chega aos pedaços. Um marcador pode vir partido ("[CP" e
 * depois "F_1]"): o pedaço que pode ser o começo de um marcador fica guardado até completar,
 * para a tela nunca mostrar "[CPF_1]" nem um pedaço dele.
 */
final class DeltaUnmasker
{
    private string $pending = '';

    /** @param  Closure(string): void  $onDelta */
    public function __construct(
        private readonly SecureDataVault $vault,
        private readonly Closure $onDelta,
    ) {}

    public function push(string $chunk): void
    {
        $text = $this->pending.$chunk;
        $this->pending = '';
        $open = strrpos($text, '[');

        // Um "[" sem "]" depois, que ainda pode virar um marcador ("[", "[CP", "[CPF_1"): segura.
        if ($open !== false && preg_match('/^\[[A-Z_]*\d*$/', substr($text, $open)) && strlen($text) - $open <= 20) {
            $this->pending = substr($text, $open);
            $text = substr($text, 0, $open);
        }

        if ($text !== '') {
            ($this->onDelta)($this->vault->unmask($text));
        }
    }

    /** O fim da resposta: o que ficou guardado sai como está (não era um marcador completo). */
    public function flush(): void
    {
        if ($this->pending !== '') {
            ($this->onDelta)($this->vault->unmask($this->pending));
            $this->pending = '';
        }
    }
}
