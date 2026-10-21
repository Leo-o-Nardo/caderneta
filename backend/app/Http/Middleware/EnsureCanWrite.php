<?php

namespace App\Http\Middleware;

use App\Support\HouseholdContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia POST/PUT/PATCH/DELETE para quem é só leitura na casa atual. Precisa rodar depois
 * do middleware "household", que preenche o contexto.
 */
class EnsureCanWrite
{
    public function __construct(private readonly HouseholdContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() && ! $this->context->role()->canWrite()) {
            abort(403);
        }

        return $next($request);
    }
}
