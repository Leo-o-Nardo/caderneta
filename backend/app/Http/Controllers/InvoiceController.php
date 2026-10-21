<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Models\Card;
use App\Services\InvoiceService;
use App\Support\InvoiceCalendar;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /** Sem ?month, mostra a fatura em aberto hoje (a que receberia uma compra feita agora). */
    public function show(Request $request, Card $card, InvoiceService $invoices): InvoiceResource
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $timezone = $request->user()->timezone;

        $period = isset($data['month'])
            ? InvoiceCalendar::forMonth($card->closing_day, $card->due_day, Month::parse($data['month'], $timezone))
            : $card->invoiceFor(CarbonImmutable::now($timezone));

        return new InvoiceResource([
            'card' => $card->load('user'),
            'period' => $period,
            'items' => $invoices->items($card, $period),
        ]);
    }
}
