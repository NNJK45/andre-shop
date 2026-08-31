<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return NotificationResource::collection(
            $request->user()->notifications()->latest()->paginate(),
        );
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $item->forceFill(['read_at' => now()])->save();
        $item->refresh();

        return response()->json([
            'data' => [
                'id' => $item->id,
                'type' => $item->type,
                'data' => $item->data,
                'read_at' => $item->read_at?->toISOString(),
                'created_at' => $item->created_at?->toISOString(),
            ],
        ]);
    }
}
