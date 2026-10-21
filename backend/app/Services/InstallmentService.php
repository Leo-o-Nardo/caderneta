<?php

namespace App\Services;

use App\Enums\CategoryKind;
use App\Enums\PaymentType;
use App\Enums\TransactionSource;
use App\Jobs\CheckBudgetAlerts;
use App\Models\Card;
use App\Models\InstallmentPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class InstallmentService
{
    /**
     * Cria o parcelamento e uma transação por parcela. A parcela N conta no mês da compra + N-1
     * e cai na fatura seguinte à da parcela anterior. Aceita o total ("2.500 em 10x") ou o valor
     * da parcela ("10x de 250").
     */
    public function create(User $author, array $data, TransactionSource $source = TransactionSource::App): InstallmentPlan
    {
        return DB::transaction(function () use ($author, $data, $source) {
            $count = (int) $data['installments'];
            $total = isset($data['total_cents'])
                ? (int) $data['total_cents']
                : (int) $data['installment_cents'] * $count;

            $plan = new InstallmentPlan([
                ...Arr::only($data, ['description', 'category_id', 'card_id', 'installments', 'purchased_on', 'visibility']),
                'total_cents' => $total,
            ]);
            $plan->created_by = $author->id;
            $plan->source = $source;
            $plan->save();

            $card = Card::query()->findOrFail($plan->card_id);
            $firstInvoice = $card->invoiceFor($plan->purchased_on)->month;

            foreach (Money::split($total, $count) as $index => $amount) {
                $transaction = new Transaction([
                    'kind' => CategoryKind::Expense,
                    'amount_cents' => $amount,
                    'description' => $plan->description,
                    // "NoOverflow": 31/01 + 1 mês = 28/02, e não 03/03.
                    'occurred_on' => $plan->purchased_on->addMonthsNoOverflow($index),
                    'category_id' => $plan->category_id,
                    'payment_type' => PaymentType::CreditCard,
                    'card_id' => $plan->card_id,
                    'visibility' => $plan->visibility,
                ]);

                $transaction->forceFill([
                    'created_by' => $author->id,
                    'source' => $source,
                    'invoice_month' => $firstInvoice->addMonthsNoOverflow($index),
                    'installment_plan_id' => $plan->id,
                    'installment_number' => $index + 1,
                ])->save();
            }

            // A primeira parcela conta no mês da compra: o orçamento da categoria é conferido.
            CheckBudgetAlerts::dispatchFor($plan->household_id, $plan->category_id, $plan->purchased_on);

            return $plan;
        });
    }

    /** Descrição, categoria e visibilidade mudam no plano e em todas as parcelas. */
    public function update(InstallmentPlan $plan, array $data): InstallmentPlan
    {
        return DB::transaction(function () use ($plan, $data) {
            $plan->update($data);

            $plan->transactions()->update([
                'description' => $plan->description,
                'category_id' => $plan->category_id,
                'visibility' => $plan->visibility->value,
            ]);

            // Mudou de categoria (ou deixou de ser privado): a parcela deste mês pesa em outro orçamento.
            if ($plan->wasChanged(['category_id', 'visibility'])) {
                CheckBudgetAlerts::dispatchFor($plan->household_id, $plan->category_id, now());
            }

            return $plan;
        });
    }

    /** As parcelas são apagadas junto, pela chave estrangeira com cascade. */
    public function delete(InstallmentPlan $plan): void
    {
        $plan->delete();
    }
}
