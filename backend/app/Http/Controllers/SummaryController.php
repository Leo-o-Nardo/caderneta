<?php

namespace App\Http\Controllers;

use App\Services\MonthSummary;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    /** Sem ?month, resume o mês atual no fuso do usuário. */
    public function show(Request $request, MonthSummary $summary): JsonResponse
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $timezone = $request->user()->timezone;

        return response()->json($summary->for(
            Month::parse($data['month'] ?? null, $timezone),
            CarbonImmutable::now($timezone),
        ));
    }
}
