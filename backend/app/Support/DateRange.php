<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Um período de datas (as duas pontas incluídas) e o jeito de dizê-lo em português. O assistente
 * sempre fala o período que considerou: "de 1º a 9 de outubro", "em setembro", "ontem".
 */
final readonly class DateRange
{
    public const MONTHS = [
        1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
    ];

    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    /** "2026-10-01" e "2026-10-09" no fuso de quem perguntou. */
    public static function parse(string $from, string $to, string $timezone): self
    {
        return new self(
            CarbonImmutable::parse($from, $timezone)->startOfDay(),
            CarbonImmutable::parse($to, $timezone)->startOfDay(),
        );
    }

    /** O mês inteiro de $month. */
    public static function month(CarbonImmutable $month): self
    {
        return new self($month->startOfMonth()->startOfDay(), $month->endOfMonth()->startOfDay());
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /** "hoje", "ontem", "em setembro", "de 1º a 9 de outubro", "de 28/09 a 04/10". */
    public function label(CarbonImmutable $today): string
    {
        $today = $today->startOfDay();

        if ($this->from->isSameDay($this->to)) {
            return match (true) {
                $this->from->isSameDay($today) => 'hoje',
                $this->from->isSameDay($today->subDay()) => 'ontem',
                default => 'em '.$this->from->format('d/m'),
            };
        }

        $sameMonth = $this->from->isSameMonth($this->to);

        if ($sameMonth && $this->from->day === 1 && $this->to->isSameDay($this->to->endOfMonth())) {
            return 'em '.self::monthLabel($this->from, $today);
        }

        if ($sameMonth) {
            return sprintf('de %s a %d de %s', self::dayLabel($this->from), $this->to->day, self::MONTHS[$this->to->month]);
        }

        return sprintf('de %s a %s', $this->from->format('d/m'), $this->to->format('d/m'));
    }

    /** "outubro", ou "outubro de 2025" quando não é o ano corrente. */
    public static function monthLabel(CarbonImmutable $month, CarbonImmutable $today): string
    {
        $name = self::MONTHS[$month->month];

        return $month->year === $today->year ? $name : "{$name} de {$month->year}";
    }

    /** O dia 1º leva o "º", como se escreve no Brasil. */
    private static function dayLabel(CarbonImmutable $date): string
    {
        return $date->day === 1 ? '1º' : (string) $date->day;
    }
}
