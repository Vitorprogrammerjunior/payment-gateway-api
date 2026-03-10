<?php

namespace App\Services\Gateway\Drivers;

use App\Services\Gateway\Drivers\AbstractGatewayDriver;
use App\Services\Gateway\ChargeRequest;
use App\Services\Gateway\ChargeResponse;
use App\Services\Gateway\GatewayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Authenticates via Bearer token obtained from POST /login (URL configured via GATEWAY1_URL).
class Gateway1Driver extends AbstractGatewayDriver
{
    private string $baseUrl;
    private string $email;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = config('gateways.gateway1.url');
        $this->email   = config('gateways.gateway1.email');
        $this->token   = config('gateways.gateway1.token');
    }

    public function charge(ChargeRequest $request): ChargeResponse
    {
        $bearerToken = $this->authenticate();

        $response = Http::withToken($bearerToken)
            ->post("{$this->baseUrl}/transactions", [
                'amount'     => $request->amount,
                'name'       => $request->clientName,
                'email'      => $request->clientEmail,
                'cardNumber' => $request->cardNumber,
                'cvv'        => $request->cvv,
            ]);

        if ($response->failed()) {
            Log::warning('Gateway1 charge failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new GatewayException('Gateway 1 charge failed: ' . $response->body());
        }

        $data = $response->json();

        return new ChargeResponse(
            externalId:      (string) ($data['id'] ?? $data['transactionId'] ?? ''),
            cardLastNumbers: $this->extractLastFour($request->cardNumber),
        );
    }

    public function refund(string $externalId): void
    {
        $bearerToken = $this->authenticate();

        $response = Http::withToken($bearerToken)
            ->post("{$this->baseUrl}/transactions/{$externalId}/charge_back");

        if ($response->failed()) {
            Log::warning('Gateway1 refund failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new GatewayException('Gateway 1 refund failed: ' . $response->body());
        }
    }

    private function authenticate(): string
    {
        $response = Http::post("{$this->baseUrl}/login", [
            'email' => $this->email,
            'token' => $this->token,
        ]);

        if ($response->failed()) {
            throw new GatewayException('Gateway 1 authentication failed.');
        }

        return $response->json('token') ?? $response->json('access_token') ?? '';
    }

}
