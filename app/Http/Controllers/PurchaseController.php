<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseRequest;
use App\Models\Transaction;
use App\Services\Gateway\GatewayException;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;

class PurchaseController extends Controller
{
    public function __construct(private readonly PurchaseService $purchaseService) {}

    public function store(PurchaseRequest $request): JsonResponse
    {
        try {
            $transaction = $this->purchaseService->purchase($request->validated());

            return response()->json($transaction->load(['client', 'gateway', 'products.product']), 201);
        } catch (GatewayException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function index(): JsonResponse
    {
        $transactions = Transaction::with(['client', 'gateway', 'products.product'])
            ->latest()
            ->paginate(20);

        return response()->json($transactions);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        return response()->json(
            $transaction->load(['client', 'gateway', 'products.product'])
        );
    }

    public function refund(Transaction $transaction): JsonResponse
    {
        try {
            $refunded = $this->purchaseService->refund($transaction);

            return response()->json($refunded);
        } catch (GatewayException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
