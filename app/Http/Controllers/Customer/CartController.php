<?php

namespace App\Http\Controllers\Customer;

use App\Application\Cart\CartService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AddCartItemRequest;
use App\Http\Requests\Customer\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request, CartService $cart): JsonResponse
    {
        return (new CartResource($cart->get($request->user())))
            ->response()
            ->setStatusCode(200);
    }

    public function add(AddCartItemRequest $request, CartService $cart): CartResource
    {
        return new CartResource($cart->add($request->user(), $request->validated()));
    }

    public function update(
        UpdateCartItemRequest $request,
        int $cartItem,
        CartService $cart,
    ): CartResource {
        return new CartResource(
            $cart->update($request->user(), $cartItem, $request->integer('quantity')),
        );
    }

    public function remove(Request $request, int $cartItem, CartService $cart): CartResource
    {
        return new CartResource($cart->remove($request->user(), $cartItem));
    }

    public function clear(Request $request, CartService $cart): CartResource
    {
        return new CartResource($cart->clear($request->user()));
    }
}
