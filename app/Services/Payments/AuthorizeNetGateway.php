<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class AuthorizeNetGateway
{
    public function isConfigured(): bool
    {
        return filled(config('payments.authorize_net.api_login_id'))
            && filled(config('payments.authorize_net.transaction_key'));
    }

    /**
     * Charge a card for an amount. Accepts opaque payment data or raw test PAN fields.
     *
     * @param  array{amount: float|string, invoice_number?: string, description?: string, opaque_data?: array, card?: array}  $payload
     * @return array{success: bool, transaction_id: string|null, message: string, raw: array}
     */
    public function charge(array $payload): array
    {
        if (! $this->isConfigured()) {
            if (! config('payments.authorize_net.allow_stub', true)) {
                throw new RuntimeException('Authorize.net is not configured.');
            }

            return [
                'success' => true,
                'transaction_id' => 'STUB-'.Str::upper(Str::random(12)),
                'message' => 'Stubbed Authorize.net sandbox approval (credentials not configured).',
                'raw' => ['stub' => true, 'payload' => [
                    'amount' => $payload['amount'],
                    'invoice_number' => $payload['invoice_number'] ?? null,
                ]],
            ];
        }

        $endpoint = config('payments.authorize_net.sandbox')
            ? 'https://apitest.authorize.net/xml/v1/request.api'
            : 'https://api.authorize.net/xml/v1/request.api';

        $payment = [];
        if (! empty($payload['opaque_data']['dataValue'])) {
            $payment['opaqueData'] = [
                'dataDescriptor' => $payload['opaque_data']['dataDescriptor'] ?? 'COMMON.ACCEPT.INAPP.PAYMENT',
                'dataValue' => $payload['opaque_data']['dataValue'],
            ];
        } elseif (! empty($payload['card']['number'])) {
            $payment['creditCard'] = [
                'cardNumber' => $payload['card']['number'],
                'expirationDate' => $payload['card']['expiration'],
                'cardCode' => $payload['card']['cvv'] ?? null,
            ];
        } else {
            throw new RuntimeException('Payment method data is required.');
        }

        $body = [
            'createTransactionRequest' => [
                'merchantAuthentication' => [
                    'name' => config('payments.authorize_net.api_login_id'),
                    'transactionKey' => config('payments.authorize_net.transaction_key'),
                ],
                'refId' => Str::limit($payload['invoice_number'] ?? Str::random(8), 20, ''),
                'transactionRequest' => [
                    'transactionType' => 'authCaptureTransaction',
                    'amount' => number_format((float) $payload['amount'], 2, '.', ''),
                    'payment' => $payment,
                    'order' => [
                        'invoiceNumber' => Str::limit($payload['invoice_number'] ?? '', 20, ''),
                        'description' => Str::limit($payload['description'] ?? 'Invoice payment', 255, ''),
                    ],
                ],
            ],
        ];

        $response = Http::asJson()->post($endpoint, $body);
        $raw = $response->json() ?? [];
        $resultCode = data_get($raw, 'messages.resultCode');
        $transId = data_get($raw, 'transactionResponse.transId');
        $responseCode = data_get($raw, 'transactionResponse.responseCode');
        $message = data_get($raw, 'transactionResponse.messages.0.description')
            ?? data_get($raw, 'messages.message.0.text')
            ?? 'Authorize.net response';

        $success = $resultCode === 'Ok' && in_array((string) $responseCode, ['1', '4'], true);

        return [
            'success' => $success,
            'transaction_id' => $transId ? (string) $transId : null,
            'message' => $message,
            'raw' => $raw,
        ];
    }
}
