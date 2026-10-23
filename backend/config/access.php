<?php

/*
| Quem pode entrar no app. Quem se cadastra sozinho espera a liberação do administrador, que recebe
| um e-mail com o link para liberar ou recusar. Quem vem por convite de alguém da casa entra direto
| (o administrador só é avisado).
*/

return [

    // Quem recebe os pedidos de cadastro (e as respostas do assistente reportadas no Chat).
    'admin_email' => env('ADMIN_EMAIL'),

    // Quem responde pelos dados na política de privacidade (/privacidade): a pessoa ou a empresa que
    // publica o app nas lojas. E o contato que aparece lá; sem ele, vale o do administrador.
    'privacy_controller' => env('PRIVACY_CONTROLLER', 'Leonardo Ferreira'),
    'contact_email' => env('CONTACT_EMAIL', env('ADMIN_EMAIL')),

    // Por quantos dias o link do e-mail para liberar ou recusar continua valendo.
    'review_link_days' => 7,

];
