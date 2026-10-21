<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Redesign "Caderneta", etapa 4: as cores neon das categorias e dos cartões viram os tons de
 * pigmento do guia (docs/design-guide.html, parte 02), de um para um. Converte também a cor que as
 * fichas do chat guardaram, para o histórico não ficar com ícones neon. Uma cor fora da tabela
 * (cadastrada direto pela API) fica como está. O down desfaz a troca.
 */
return new class extends Migration
{
    // A tabela do guia, escrita aqui (e não lida do app) para a migration não mudar se a paleta mudar depois.
    private const PIGMENTS = [
        '#3DD68C' => '#4F7F4F', // Louro
        '#2DD4BF' => '#3A8577', // Verde-água
        '#22D3EE' => '#357A8F', // Petróleo
        '#4C8DFF' => '#4A6A9A', // Ardósia
        '#818CF8' => '#5A5A9A', // Índigo
        '#A78BFA' => '#7E5490', // Ameixa
        '#C084FC' => '#9A4A72', // Vinho
        '#F472B6' => '#B5677E', // Rosa-argila
        '#F87171' => '#B9553A', // Telha
        '#FB923C' => '#C27A3A', // Canela
        '#F5B94A' => '#B8902F', // Açafrão
        '#FACC15' => '#7F8A3A', // Oliva
        '#A3E635' => '#8A6A50', // Castanho
        '#9AA1AB' => '#7D786D', // Pedra
    ];

    // As colunas jsonb onde as fichas do chat e as ações do assistente guardam a cor da categoria.
    private const SNAPSHOTS = [
        ['messages', 'metadata'],
        ['pending_actions', 'payload'],
        ['pending_actions', 'result'],
    ];

    public function up(): void
    {
        $this->convert(self::PIGMENTS);
    }

    public function down(): void
    {
        $this->convert(array_flip(self::PIGMENTS));
    }

    /**
     * Troca cada cor de $map pela correspondente, sem ligar para maiúsculas. As cores de origem e
     * de destino não se repetem entre si, então a ordem das trocas não importa.
     */
    private function convert(array $map): void
    {
        DB::transaction(function () use ($map) {
            foreach ($map as $from => $to) {
                foreach (['categories', 'cards'] as $table) {
                    DB::table($table)->whereRaw('upper(color) = ?', [$from])->update(['color' => $to]);
                }

                foreach (self::SNAPSHOTS as [$table, $column]) {
                    DB::statement(
                        "update {$table} set {$column} = regexp_replace({$column}::text, ?, ?, 'gi')::jsonb where {$column}::text ~* ?",
                        [$from, $to, $from],
                    );
                }
            }
        });
    }
};
