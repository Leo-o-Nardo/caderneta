<?php

namespace App\Alerts;

use App\Models\PushToken;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Manda notificações pelo serviço de push da Expo, que entrega no Android (Firebase) e no iPhone
 * (Apple). Um pedido leva até 100 notificações; a resposta traz um recibo por notificação, na mesma
 * ordem. O aparelho de onde o app foi desinstalado volta como "DeviceNotRegistered" e sai da lista.
 */
final class ExpoPush
{
    // O máximo que a Expo aceita num pedido só.
    private const BATCH = 100;

    /**
     * Manda a mesma notificação para cada endereço. Devolve quantas a Expo aceitou. Uma falha do
     * serviço (fora do ar, limite de pedidos) vira exceção, para a fila tentar de novo.
     *
     * @param  list<string>  $tokens
     * @param  array<string, mixed>  $data  O que o app recebe junto, para abrir no lugar certo.
     *
     * @throws RequestException
     */
    public function send(array $tokens, string $title, string $body, array $data = []): int
    {
        $accepted = 0;

        foreach (array_chunk($tokens, self::BATCH) as $batch) {
            $messages = array_map(fn (string $token) => [
                'to' => $token,
                'title' => $title,
                'body' => $body,
                'data' => (object) $data,
                'sound' => 'default',
                'priority' => 'high',
                // O canal que o app cria no Android (ver src/lib/push.ts).
                'channelId' => 'avisos',
            ], $batch);

            $response = Http::acceptJson()
                ->timeout(15)
                ->when(config('services.expo.access_token'), fn ($request, string $token) => $request->withToken($token))
                ->post(config('services.expo.push_url'), $messages)
                ->throw();

            foreach ($response->json('data', []) as $index => $ticket) {
                if (($ticket['status'] ?? null) === 'ok') {
                    $accepted++;
                } elseif (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                    PushToken::query()->where('token', $batch[$index])->delete();
                }
            }
        }

        return $accepted;
    }
}
