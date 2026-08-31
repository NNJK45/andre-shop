<?php

namespace App\Http\Controllers\Admin;

use App\Application\Quote\QuoteService;
use App\Domain\Quote\Enums\QuoteStatus;
use App\Domain\Quote\Models\QuoteRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuoteRequestRequest;
use App\Http\Requests\Admin\UpdateQuoteStatusRequest;
use App\Http\Resources\QuoteRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class QuoteRequestController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return QuoteRequestResource::collection(
            QuoteRequest::query()->with(['supplier', 'requester', 'items.product', 'items.productVariant'])->latest()->paginate(),
        );
    }

    public function store(QuoteRequestRequest $request, QuoteService $quotes): JsonResponse
    {
        $quote = $quotes->create($request->validated(), $request->user());

        return (new QuoteRequestResource($quote))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(QuoteRequest $quoteRequest): QuoteRequestResource
    {
        return new QuoteRequestResource($quoteRequest->load(['supplier', 'requester', 'items.product', 'items.productVariant']));
    }

    public function updateStatus(
        UpdateQuoteStatusRequest $request,
        QuoteRequest $quoteRequest,
        QuoteService $quotes,
    ): QuoteRequestResource {
        return new QuoteRequestResource(
            $quotes->transition($quoteRequest, $request->enum('status', QuoteStatus::class)),
        );
    }
}
