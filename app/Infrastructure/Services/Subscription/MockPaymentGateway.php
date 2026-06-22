<?php

namespace App\Infrastructure\Services\Subscription;

class MockPaymentGateway
{
    public function charge(float $amount, array $metadata = []): array
    {
        return [
            'success' => true,
            'transaction_id' => 'mock_'.uniqid(),
            'amount' => $amount,
            'metadata' => $metadata,
        ];
    }
}
