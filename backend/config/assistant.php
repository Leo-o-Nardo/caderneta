<?php

use App\Assistant\Tools\AddListItems;
use App\Assistant\Tools\CheckListItems;
use App\Assistant\Tools\CreateList;
use App\Assistant\Tools\CreateRecurringBill;
use App\Assistant\Tools\CreateRecurringIncome;
use App\Assistant\Tools\CreateReminder;
use App\Assistant\Tools\CreateTransactions;
use App\Assistant\Tools\DeleteList;
use App\Assistant\Tools\DeleteTransaction;
use App\Assistant\Tools\GetBudgetStatus;
use App\Assistant\Tools\GetCardInvoice;
use App\Assistant\Tools\GetList;
use App\Assistant\Tools\GetMonthOverview;
use App\Assistant\Tools\ListBills;
use App\Assistant\Tools\ListReminders;
use App\Assistant\Tools\MarkBillPaid;
use App\Assistant\Tools\QueryTransactions;
use App\Assistant\Tools\SetBudget;
use App\Assistant\Tools\UpdateTransaction;

/*
| O assistente da casa: qual IA responde, quanto do histórico ela vê e os limites de uso.
*/

return [

    // "fake" responde por regras, sem chave nem internet (testes e modo demo); "gemini" é a IA real.
    'provider' => env('ASSISTANT_PROVIDER', 'fake'),

    // Quem transforma as mensagens de voz em texto: "gemini" (a mesma chave do assistente) ou
    // "fake" (só nos testes automáticos: devolve sempre a frase de fake.transcript).
    'transcriber' => env('ASSISTANT_TRANSCRIBER', 'gemini'),

    'voice' => [
        // Até 60 s por gravação; o app para sozinho ao chegar lá.
        'max_seconds' => 60,
        // Um minuto de voz no formato do app tem ~250 KB; 2 MB dá folga para outros formatos.
        'max_kb' => 2048,
    ],

    // O cofre: CPF, CNPJ, cartão, telefone, e-mail, chave Pix e agência/conta viram marcadores
    // ("[CPF_1]") antes de ir para a IA, e voltam ao valor real na resposta. Vale para qualquer IA.
    'vault' => (bool) env('ASSISTANT_VAULT', true),

    'gemini' => [
        // Criada no Google AI Studio. Com o faturamento ativo, o Google não usa as conversas para treinar.
        'api_key' => env('GEMINI_API_KEY'),
        // O Flash-Lite: o mais barato da linha, escolhido para o uso do dia a dia.
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        // Quanto a IA "pensa" antes de responder. "low" acerta melhor as ferramentas que "minimal"
        // (o padrão do Flash-Lite) e ainda sai barato e rápido. O 3.8 Flash não aceita "minimal".
        'thinking_level' => env('GEMINI_THINKING_LEVEL', 'low'),
        // Transcrever não precisa raciocinar: "minimal" deixa mais rápido e barato. Se trocar o
        // modelo para o 3.8 Flash, use "low" (ele não aceita "minimal").
        'transcription_thinking_level' => env('GEMINI_TRANSCRIPTION_THINKING_LEVEL', 'minimal'),
        // Inclui os tokens de raciocínio.
        'max_output_tokens' => 4096,
        'connect_timeout' => 5,
        // Segundos para a resposta inteira (o job tem 120).
        'timeout' => 60,
        // Novas tentativas quando o Gemini está ocupado (429/503), com a espera crescendo a cada uma.
        'retries' => 1,
        'retry_delay_ms' => 1500,
    ],

    // Preço em dólares por 1 milhão de tokens, para estimar o custo de cada rodada (tabela llm_usage).
    // O raciocínio é cobrado como saída. O preço do 3.8 Flash dobra em 01/01/2027.
    'prices' => [
        'gemini-3.8-flash' => ['input' => 0.75, 'output' => 3.75],
        'gemini-3.5-flash-lite' => ['input' => 0.30, 'output' => 2.50],
    ],

    // Quantas vezes a IA pode pedir ferramentas antes de responder (evita laço infinito).
    'max_rounds' => 5,

    // Quantas mensagens anteriores vão junto como contexto.
    'history' => 12,

    'max_chars' => 2000,

    // Mensagens por pessoa por dia (no fuso dela).
    'daily_limit' => (int) env('ASSISTANT_DAILY_LIMIT', 150),

    // De quanto em quanto tempo os pedaços de texto são enviados ao app (juntar evita mandar uma palavra por evento).
    'stream_flush_ms' => 100,

    'fake' => [
        // Pausa entre as palavras da resposta simulada, para o streaming aparecer na tela. Zero nos testes.
        'chunk_delay_ms' => (int) env('ASSISTANT_FAKE_DELAY_MS', 40),
        // O que o transcritor simulado (só nos testes) "ouve" em toda mensagem de voz.
        'transcript' => 'quanto gastei esse mês?',
    ],

    'actions' => [
        // Quanto tempo um card de confirmação (lançar, pagar, apagar) continua valendo.
        'confirm_minutes' => 30,
        // Quanto tempo o "Desfazer" fica disponível nas ações feitas na hora (lista, lembrete).
        'undo_seconds' => 120,
    ],

    /*
    | As ferramentas que a IA pode usar. Uma ferramenta nova é uma classe nova e uma linha aqui.
    */
    'tools' => [
        // Consultas.
        QueryTransactions::class,
        GetMonthOverview::class,
        GetCardInvoice::class,
        ListBills::class,
        GetBudgetStatus::class,
        GetList::class,
        ListReminders::class,

        // Ações (ver ActionService). Com card para confirmar:
        CreateTransactions::class,
        UpdateTransaction::class,
        MarkBillPaid::class,
        DeleteTransaction::class,
        DeleteList::class,
        // Gravadas na hora, com "Desfazer":
        AddListItems::class,
        CheckListItems::class,
        CreateReminder::class,
        CreateList::class,
        CreateRecurringBill::class,
        CreateRecurringIncome::class,
        SetBudget::class,
    ],

];
