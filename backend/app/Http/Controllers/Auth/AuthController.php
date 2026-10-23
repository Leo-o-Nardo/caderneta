<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\DeleteAccountRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AccessService;
use App\Services\AccountService;
use App\Services\HouseholdService;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Por convite, a conta entra liberada e já recebe o login (201). Sem convite, fica esperando o
     * administrador liberar (202, sem login): a pessoa recebe um e-mail quando for liberada.
     */
    public function register(
        RegisterRequest $request,
        HouseholdService $households,
        InvitationService $invitations,
        AccessService $access,
    ): JsonResponse {
        $code = $request->validated('invite_code');
        $invitation = $code ? $invitations->find($code) : null;

        if ($code && ! $invitation) {
            throw ValidationException::withMessages(['invite_code' => __('households.invitation_invalid')]);
        }

        $user = DB::transaction(function () use ($request, $households, $invitations, $code) {
            $user = User::create($request->safe()->only(['name', 'email', 'password']));

            // Quem se cadastra por um convite entra direto na casa do convite.
            if ($code) {
                $invitations->accept($code, $user);
            } else {
                $households->createDefaultFor($user);
            }

            return $user;
        });

        if ($invitation) {
            $access->joinedByInvite($user, $invitation);

            return $this->tokenResponse($user, $request->validated('device_name'), 201);
        }

        $access->request($user);

        return response()->json(['status' => 'pending', 'message' => __('auth.access_requested')], 202);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        // A senha está certa, mas a conta ainda espera a liberação (ou foi recusada). A chave diz qual
        // (access_pending ou access_rejected): o app mostra a espera como aviso, e não como erro.
        if ($user->accessStatus() !== 'approved') {
            $key = 'access_'.$user->accessStatus();

            throw ValidationException::withMessages([$key => __('auth.'.$key)]);
        }

        return $this->tokenResponse($user, $request->validated('device_name'));
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /** O nome (e o fuso) de quem está logado. */
    public function updateMe(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $data = $request->validated();

        if (isset($data['alert_settings'])) {
            // Só os avisos enviados mudam; os outros ficam como estavam.
            $data['alert_settings'] = array_merge($user->alert_settings ?? [], array_map(boolval(...), $data['alert_settings']));
        }

        $user->update($data);

        return new UserResource($user);
    }

    /** Exclui a conta de quem está logado (ver AccountService). O app volta para a tela de entrar. */
    public function destroyMe(DeleteAccountRequest $request, AccountService $accounts): Response
    {
        $accounts->delete($request->user());

        return response()->noContent();
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function logoutAll(Request $request): Response
    {
        $request->user()->tokens()->delete();

        return response()->noContent();
    }

    private function tokenResponse(User $user, string $deviceName, int $status = 200): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken($deviceName)->plainTextToken,
            'user' => new UserResource($user),
        ], $status);
    }
}
