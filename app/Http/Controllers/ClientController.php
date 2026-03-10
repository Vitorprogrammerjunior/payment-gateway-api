<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\JsonResponse;

class ClientController extends Controller
{
    public function index(): JsonResponse
    {
        $clients = Client::withCount('transactions')->latest()->paginate(20);

        return response()->json($clients);
    }

    public function show(Client $client): JsonResponse
    {
        return response()->json(
            $client->load(['transactions.gateway', 'transactions.products.product'])
        );
    }
}
