<?php

namespace App\Assistant\Observers;

use App\Assistant\TurnObserver;
use App\Models\Message;
use Symfony\Component\Console\Output\OutputInterface;

/** Mostra o turno no terminal (comando assistant:ask): status em cinza, texto chegando, cards em JSON. */
final class ConsoleObserver implements TurnObserver
{
    public function __construct(private readonly OutputInterface $output) {}

    public function status(Message $reply, string $text): void
    {
        $this->output->writeln("<fg=gray>{$text}</>");
    }

    public function delta(Message $reply, string $chunk): void
    {
        $this->output->write($chunk);
    }

    public function completed(Message $reply): void
    {
        $this->output->writeln('');

        foreach ($reply->cards() as $card) {
            $this->output->writeln('');
            $this->output->writeln("<fg=cyan>card {$card['type']}</>");
            $this->output->writeln(json_encode($card, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    public function failed(Message $reply): void
    {
        $this->output->writeln('');
        $this->output->writeln("<fg=red>{$reply->content}</>");
    }
}
