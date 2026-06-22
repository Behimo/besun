<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Integrations\WooCommerceBridgeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WooCommerceBridgeController extends Controller
{
    public function ping(Request $request, string $token, WooCommerceBridgeService $bridge): JsonResponse
    {
        $connection = $this->resolveConnection($token, $bridge, $request);

        $payload = json_decode($request->getContent(), true) ?: [];
        $result = $bridge->ping($connection, is_array($payload) ? $payload : []);

        return response()->json($result);
    }

    public function syncProducts(Request $request, string $token, WooCommerceBridgeService $bridge): JsonResponse
    {
        $connection = $this->resolveConnection($token, $bridge, $request);

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'بدنه درخواست نامعتبر است.'], 422);
        }

        return response()->json($bridge->syncProducts($connection, $payload));
    }

    public function syncOrders(Request $request, string $token, WooCommerceBridgeService $bridge): JsonResponse
    {
        $connection = $this->resolveConnection($token, $bridge, $request);

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'بدنه درخواست نامعتبر است.'], 422);
        }

        return response()->json($bridge->syncOrders($connection, $payload));
    }

    public function pushOrder(Request $request, string $token, WooCommerceBridgeService $bridge): JsonResponse
    {
        $connection = $this->resolveConnection($token, $bridge, $request);

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload) || empty($payload['id'])) {
            return response()->json(['message' => 'بدنه سفارش نامعتبر است.'], 422);
        }

        return response()->json($bridge->pushOrder($connection, $payload));
    }

    public function commands(Request $request, string $token, WooCommerceBridgeService $bridge): JsonResponse
    {
        $connection = $this->resolveConnection($token, $bridge, $request);

        return response()->json($bridge->pollCommands($connection));
    }

    public function ackCommands(Request $request, string $token, WooCommerceBridgeService $bridge): JsonResponse
    {
        $connection = $this->resolveConnection($token, $bridge, $request);

        $payload = json_decode($request->getContent(), true) ?: [];

        return response()->json($bridge->ackCommands($connection, is_array($payload) ? $payload : []));
    }

    protected function resolveConnection(string $token, WooCommerceBridgeService $bridge, Request $request)
    {
        $connection = $bridge->resolveConnection($token);

        if (! $connection) {
            abort(404, 'اتصال یافت نشد.');
        }

        $bridge->assertBridgeAccess($connection);
        $bridge->verifyRequest(
            $request->getContent(),
            $request->header('X-Rahbar-Signature'),
            $connection,
        );

        return $connection;
    }
}
