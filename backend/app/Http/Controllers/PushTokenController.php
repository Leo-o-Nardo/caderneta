<?php

namespace App\Http\Controllers;

use App\Http\Requests\Alerts\StorePushTokenRequest;
use App\Models\PushToken;
use Illuminate\Http\Response;

class PushTokenController extends Controller
{
    /**
     * O app manda o endereço do celular a cada abertura (ele pode mudar). O mesmo celular nunca
     * fica com duas pessoas: se outra conta entrar nele, o endereço passa para ela. Não há rota
     * para tirar: sair da conta apaga o login, e o endereço vai junto (ver a migração).
     */
    public function store(StorePushTokenRequest $request): Response
    {
        PushToken::query()->updateOrCreate(
            ['token' => $request->validated('token')],
            [
                'user_id' => $request->user()->id,
                'personal_access_token_id' => $request->user()->currentAccessToken()->getKey(),
                'platform' => $request->validated('platform'),
            ],
        );

        return response()->noContent();
    }
}
