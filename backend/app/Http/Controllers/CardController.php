<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\StoreCardRequest;
use App\Http\Requests\Finance\UpdateCardRequest;
use App\Http\Resources\CardResource;
use App\Models\Card;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CardController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CardResource::collection(Card::active()->with('user')->orderBy('name')->get());
    }

    public function store(StoreCardRequest $request): JsonResponse
    {
        $user = $request->user();

        $card = new Card($request->safe()->except('is_default'));
        $card->user()->associate($user);
        $card->save();

        $isFirst = ! Card::active()->where('user_id', $user->id)->whereKeyNot($card->id)->exists();

        if ($isFirst || $request->boolean('is_default')) {
            $card->makeDefault();
        }

        return (new CardResource($card->load('user')))->response()->setStatusCode(201);
    }

    public function update(UpdateCardRequest $request, Card $card): CardResource
    {
        Gate::authorize('update', $card);

        $card->update($request->safe()->except('is_default'));

        if ($request->boolean('is_default')) {
            $card->makeDefault();
        }

        return new CardResource($card->load('user'));
    }

    public function destroy(Card $card): Response
    {
        Gate::authorize('delete', $card);

        $card->archive();

        return response()->noContent();
    }
}
