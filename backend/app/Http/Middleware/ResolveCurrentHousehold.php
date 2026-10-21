<?php

namespace App\Http\Middleware;

use App\Services\HouseholdService;
use App\Support\HouseholdContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentHousehold
{
    public function __construct(
        private readonly HouseholdService $households,
        private readonly HouseholdContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $household = $this->households->ensureCurrent($request->user());

        $this->context->set($household, $household->membership->role, $request->user());

        return $next($request);
    }
}
