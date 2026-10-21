<?php

/*
| Quem pode entrar no app. Quem se cadastra sozinho espera a liberação do administrador, que recebe
| um e-mail com o link para liberar ou recusar. Quem vem por convite de alguém da casa entra direto
| (o administrador só é avisado).
*/

return [

    // Quem recebe os pedidos de cadastro.
    'admin_email' => env('ADMIN_EMAIL'),

    // Por quantos dias o link do e-mail para liberar ou recusar continua valendo.
    'review_link_days' => 7,

];
