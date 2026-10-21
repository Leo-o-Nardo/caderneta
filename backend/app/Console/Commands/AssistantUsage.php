<?php

namespace App\Console\Commands;

use App\Models\LlmUsage;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Quanto a IA gastou: mensagens, chamadas, tokens, erros e custo estimado, por modelo e por uso
 * (o assistente ou a transcrição das mensagens de voz). Os períodos seguem o fuso do app
 * (America/Sao_Paulo).
 *
 *   php artisan assistant:usage            hoje e o mês atual
 *   php artisan assistant:usage --days=7   os últimos 7 dias
 */
class AssistantUsage extends Command
{
    protected $signature = 'assistant:usage {--days= : Os últimos N dias, em vez de hoje e do mês}';

    protected $description = 'Mostra o uso e o custo estimado da IA (tabela llm_usage)';

    private const TIMEZONE = 'America/Sao_Paulo';

    public function handle(): int
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $days = $this->option('days');

        $periods = $days
            ? ["Últimos {$days} dias" => $now->subDays((int) $days - 1)->startOfDay()]
            : ['Hoje' => $now->startOfDay(), 'Este mês' => $now->startOfMonth()];

        foreach ($periods as $label => $from) {
            $this->line('');
            $this->info($label);
            $this->table(
                ['Modelo', 'Uso', 'Mensagens', 'Chamadas', 'Entrada', 'Saída', 'Raciocínio', 'Erros', 'Tempo médio', 'Custo (US$)'],
                $this->rows($from),
            );
        }

        return self::SUCCESS;
    }

    private function rows(CarbonImmutable $from): array
    {
        $rows = LlmUsage::query()
            ->where('created_at', '>=', $from->utc())
            ->groupBy('model', 'purpose')
            ->orderBy('model')
            ->orderBy('purpose')
            ->get([
                'model',
                'purpose',
                DB::raw('count(distinct message_id) as questions'),
                DB::raw('count(*) as rounds'),
                DB::raw('sum(input_tokens) as input'),
                DB::raw('sum(output_tokens) as output'),
                DB::raw('sum(thinking_tokens) as thinking'),
                DB::raw('count(error) as errors'),
                DB::raw('avg(latency_ms) as latency'),
                DB::raw('sum(cost_micros) as cost'),
            ]);

        if ($rows->isEmpty()) {
            return [['—', '—', 0, 0, 0, 0, 0, 0, '—', '0,0000']];
        }

        return $rows->map(fn (LlmUsage $row) => [
            $row->model,
            $row->purpose === 'transcription' ? 'Voz' : 'Assistente',
            $row->questions,
            $row->rounds,
            number_format((int) $row->input, 0, ',', '.'),
            number_format((int) $row->output, 0, ',', '.'),
            number_format((int) $row->thinking, 0, ',', '.'),
            $row->errors,
            number_format((float) $row->latency / 1000, 1, ',', '.').' s',
            number_format((int) $row->cost / 1_000_000, 4, ',', '.'),
        ])->all();
    }
}
