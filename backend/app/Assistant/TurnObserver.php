<?php

namespace App\Assistant;

use App\Models\Message;

/**
 * Quem acompanha um turno enquanto ele acontece. O job manda tudo pelo canal do usuário (o app
 * mostra ao vivo); o comando assistant:ask imprime no terminal.
 */
interface TurnObserver
{
    /** Uma ferramenta começou: "Consultando lançamentos…". */
    public function status(Message $reply, string $text): void;

    /** Mais um pedaço do texto da resposta. */
    public function delta(Message $reply, string $chunk): void;

    public function completed(Message $reply): void;

    public function failed(Message $reply): void;
}
