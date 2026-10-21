<?php

namespace App\Services;

use App\Enums\CategoryKind;
use App\Enums\PaymentType;
use App\Enums\TransactionSource;
use App\Jobs\CheckBudgetAlerts;
use App\Models\Card;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Os dados chegam já validados (pelos Requests da API ou, depois, pelas ferramentas da IA). */
class TransactionService
{
    public function create(User $author, array $data, TransactionSource $source = TransactionSource::App): Transaction
    {
        $transaction = new Transaction($data);
        $transaction->created_by = $author->id;
        $transaction->source = $source;

        $this->applyPayment($transaction);
        $transaction->save();
        $this->checkBudget($transaction);

        return $transaction;
    }

    /**
     * Vários lançamentos de uma vez ("12 de café, 45 no almoço e 30 de uber"): ou todos são
     * gravados, ou nenhum.
     *
     * @return Collection<int, Transaction>
     */
    public function createMany(User $author, array $items, TransactionSource $source = TransactionSource::App): Collection
    {
        return DB::transaction(
            fn () => collect($items)->map(fn (array $data) => $this->create($author, $data, $source))
        );
    }

    public function update(Transaction $transaction, array $data): Transaction
    {
        $this->ensureNotInstallment($transaction);

        $transaction->fill($data);
        $this->applyPayment($transaction);
        $transaction->save();
        $this->checkBudget($transaction);

        return $transaction;
    }

    public function delete(Transaction $transaction): void
    {
        $this->ensureNotInstallment($transaction);

        $transaction->delete();
    }

    /**
     * Receita não tem forma de pagamento. Compra no cartão ganha a fatura em que cai; as outras
     * formas de pagamento não têm cartão nem fatura.
     */
    private function applyPayment(Transaction $transaction): void
    {
        if ($transaction->kind === CategoryKind::Income) {
            $transaction->payment_type = null;
        }

        if ($transaction->payment_type === PaymentType::CreditCard) {
            $card = Card::query()->findOrFail($transaction->card_id);
            $transaction->invoice_month = $card->invoiceFor($transaction->occurred_on)->month;
        } else {
            $transaction->card_id = null;
            $transaction->invoice_month = null;
        }
    }

    /** Gasto lançado ou editado: o orçamento da categoria é conferido pela fila (aviso de 80% e 100%). */
    private function checkBudget(Transaction $transaction): void
    {
        if ($transaction->kind === CategoryKind::Expense) {
            CheckBudgetAlerts::dispatchFor($transaction->household_id, $transaction->category_id, $transaction->occurred_on);
        }
    }

    /** Parcelas mudam todas juntas, pelo parcelamento. */
    private function ensureNotInstallment(Transaction $transaction): void
    {
        if ($transaction->installment_plan_id) {
            throw ValidationException::withMessages(['transaction' => __('finance.edit_through_plan')]);
        }
    }
}
