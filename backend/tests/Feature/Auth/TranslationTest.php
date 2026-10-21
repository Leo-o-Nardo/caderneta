<?php

use App\Models\User;

// O app mostra essas mensagens direto para o usuário, então elas precisam sair em português.

it('answers validation errors in Portuguese', function () {
    User::factory()->create(['email' => 'leo@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Leo',
        'email' => 'leo@example.com',
        'password' => 'abc',
        'device_name' => 'pixel',
    ])->assertJsonValidationErrors([
        'email' => 'Este e-mail já está em uso.',
        'password' => 'O campo senha deve ter pelo menos 8 caracteres.',
    ]);
});

it('answers a wrong login in Portuguese', function () {
    User::factory()->create(['email' => 'leo@example.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'leo@example.com',
        'password' => 'errada123',
        'device_name' => 'pixel',
    ])->assertJsonValidationErrors(['email' => 'E-mail ou senha incorretos.']);
});
