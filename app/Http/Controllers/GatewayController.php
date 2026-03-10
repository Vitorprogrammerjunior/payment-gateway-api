<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGatewayRequest;
use App\Models\Gateway;
use Illuminate\Http\JsonResponse;

class GatewayController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Gateway::orderBy('priority')->get());
    }

    public function update(UpdateGatewayRequest $request, Gateway $gateway): JsonResponse
    {
        $gateway->update($request->validated());

        return response()->json($gateway);
    }
}
