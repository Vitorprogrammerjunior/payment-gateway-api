<?php

namespace App\Services\Gateway\Drivers;

use App\Services\Gateway\Drivers\AbstractGatewayDriver;
use App\Services\Gateway\ChargeRequest;
use App\Services\Gateway\ChargeResponse;
use App\Services\Gateway\GatewayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Authenticates via static headers: Gateway-Auth-Token and Gateway-Auth-Secret (URL configured via GATEWAY2_URL).
class Gateway2Driver extends AbstractGatewayDriver
{
    private string $baseUrl;
    private string $authToken;
    private string $authSecret;

    public function __construct()
    {
        $this->baseUrl    = config('gateways.gateway2.url');
        $this->authToken  = config('gateways.gateway2.auth_token');
        $this->authSecret = config('gateways.gateway2.auth_secret');
    }

    public function charge(ChargeRequest $request): ChargeResponse
    {
        $response = Http::withHeaders($this->authHeaders())
            ->post("{$this->baseUrl}/transacoes", [
                'valor'        => $request->amount,
                'nome'         => $request->clientName,
                'email'        => $request->clientEmail,
                'numeroCartao' => $request->cardNumber,
                'cvv'          => $request->cvv,
            ]);

        if ($response->failed()) {
            Log::warning('Gateway2 charge failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new GatewayException('Gateway 2 charge failed: ' . $response->body());
        }

        $data = $response->json();

        return new ChargeResponse(
            externalId:      (string) ($data['id'] ?? $data['transactionId'] ?? ''),
            cardLastNumbers: $this->extractLastFour($request->cardNumber),
        );
    }

    public function refund(string $externalId): void
    {
        $response = Http::withHeaders($this->authHeaders())
            ->post("{$this->baseUrl}/transacoes/reembolso", [
                'id' => $externalId,
            ]);

        if ($response->failed()) {
            Log::warning('Gateway2 refund failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new GatewayException('Gateway 2 refund failed: ' . $response->body());
        }
    }

    private function authHeaders(): array
    {
        return [
            'Gateway-Auth-Token'  => $this->authToken,
            'Gateway-Auth-Secret' => $this->authSecret,
        ];
    }

}
