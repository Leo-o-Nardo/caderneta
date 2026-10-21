<?php

return [
    'failed' => 'Não consegui responder agora. Tente de novo em instantes.',
    'not_member' => 'Você não faz mais parte desta casa.',
    'unknown_tool' => 'Essa ferramenta não existe ou não está disponível para você.',
    'tool_failed' => 'Não consegui consultar isso agora.',
    'too_many_rounds' => 'Não consegui terminar essa resposta. Tente perguntar de outro jeito.',
    'daily_limit' => 'Você chegou ao limite de :limit mensagens por hoje. Amanhã dá para continuar.',

    // Mensagens de voz que não viraram texto (a resposta do assistente explica).
    'voice' => [
        'no_speech' => 'Não consegui entender o áudio. Tente de novo, falando perto do microfone, ou escreva.',
        'busy' => 'Não consegui transcrever o áudio agora. Tente de novo em instantes ou escreva.',
        'failed' => 'Não consegui transcrever o áudio. Tente de novo ou escreva.',
    ],

    // Quando a IA real não responde (o detalhe técnico fica no log).
    'llm' => [
        'busy' => 'A IA está ocupada agora. Tente de novo em instantes.',
        'blocked' => 'Não consigo responder isso. Tente perguntar de outro jeito.',
        'failed' => 'Não consegui responder agora. Tente de novo em instantes.',
        // Uma ação já foi preparada antes de a IA falhar: o card fica, com este aviso.
        'partial' => 'Não consegui terminar a resposta, mas o que preparei está no card abaixo.',
    ],

    // Quando a pessoa toca num botão de um card que já não vale.
    'actions' => [
        'pending' => 'Essa ação ainda espera a confirmação.',
        'executed' => 'Essa ação já foi confirmada.',
        'cancelled' => 'Essa ação foi cancelada.',
        'expired' => 'Essa confirmação expirou. Peça de novo ao assistente.',
        'undone' => 'Essa ação já foi desfeita.',
        'undo_expired' => 'Já passou o tempo para desfazer. Dá para corrigir pela tela.',
        'not_undoable' => 'Essa ação não pode ser desfeita por aqui. Dá para corrigir pela tela.',
        'gone' => 'Isso não existe mais: talvez já tenha sido apagado.',
    ],
];
