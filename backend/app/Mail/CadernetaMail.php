<?php

namespace App\Mail;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

/**
 * Um e-mail no estilo Caderneta (docs/design-guide.html): o ícone e o nome no topo, o título em
 * serifa, os textos, os dados numa tabela de pauta, o botão em Tinta e o rodapé com o motivo do
 * e-mail. Monta as duas versões, HTML e texto puro, a partir das mesmas partes:
 *
 *     CadernetaMail::make('Seu acesso à Caderneta foi liberado')
 *         ->title('Seu acesso foi liberado, Bia')
 *         ->paragraph('Já dá para entrar…')
 *         ->reason('Você recebeu este e-mail porque se cadastrou na Caderneta.')
 *         ->toMail();
 */
final class CadernetaMail
{
    // As cores do guia que mudam de uma parte para outra: o rótulo de cima e o traço do aviso.
    private const TONES = [
        'warn' => '#7F5A0C',
        'accent' => '#2C5440',
        'muted' => '#5F5B52',
        'line' => '#DCD5C6',
    ];

    private ?array $overline = null;

    private string $title = '';

    /** @var list<string> */
    private array $paragraphs = [];

    /** @var array<string, string> */
    private array $rows = [];

    private ?array $notice = null;

    private ?string $code = null;

    private ?array $button = null;

    private ?string $note = null;

    private string $reason = '';

    private function __construct(private readonly string $subject) {}

    public static function make(string $subject): self
    {
        return new self($subject);
    }

    /** O rótulo em caixa alta em cima do título ("Esperando liberação"): warn, accent ou muted. */
    public function overline(string $text, string $tone = 'muted'): self
    {
        $this->overline = ['text' => $text, 'color' => self::TONES[$tone]];

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function paragraph(string $text): self
    {
        $this->paragraphs[] = $text;

        return $this;
    }

    /** Os dados numa tabela de pauta: rótulo em Grafite à esquerda, valor à direita. */
    public function rows(array $rows): self
    {
        $this->rows = $rows;

        return $this;
    }

    /** Um aviso com o traço ao lado: a primeira parte em negrito ("Para começar:"). Traço accent ou line. */
    public function notice(string $lead, string $text, string $tone = 'line'): self
    {
        $this->notice = ['lead' => $lead, 'text' => $text, 'color' => self::TONES[$tone]];

        return $this;
    }

    /** O código grande, com o traço duplo embaixo (o de nova senha). */
    public function code(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function button(string $label, string $url): self
    {
        $this->button = ['label' => $label, 'url' => $url];

        return $this;
    }

    /** A nota em letra pequena depois do botão ou do código. O que estiver entre crases vira código. */
    public function note(string $text): self
    {
        $this->note = $text;

        return $this;
    }

    /** Por que a pessoa recebeu o e-mail, no rodapé. */
    public function reason(string $text): self
    {
        $this->reason = $text;

        return $this;
    }

    public function toMail(): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->view(['emails.caderneta', 'emails.caderneta-text'], $this->data());
    }

    /** As partes do e-mail para as duas views (resources/views/emails). */
    private function data(): array
    {
        return [
            'subject' => $this->subject,
            // A linha que o programa de e-mail mostra depois do assunto, na caixa de entrada.
            'preheader' => $this->paragraphs[0] ?? $this->title,
            'icon' => asset('images/caderneta-icone.png'),
            'overline' => $this->overline,
            'title' => $this->title,
            'paragraphs' => $this->paragraphs,
            'rows' => $this->rows,
            'notice' => $this->notice,
            'code' => $this->code,
            'button' => $this->button,
            'noteHtml' => $this->note === null ? null : new HtmlString(preg_replace(
                '/`([^`]+)`/',
                '<code style="font:500 12px/16px Consolas,Menlo,monospace;background:#ECE6DA;color:#1E1F1C;padding:1px 4px;">$1</code>',
                e($this->note),
            )),
            'noteText' => $this->note === null ? null : str_replace('`', '', $this->note),
            'reason' => $this->reason,
        ];
    }
}
