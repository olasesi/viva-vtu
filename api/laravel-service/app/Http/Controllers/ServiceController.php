<?php

namespace App\Http\Controllers;

use App\Services\VtpassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    protected VtpassService $vtpassService;

    public function __construct(VtpassService $vtpassService)
    {
        $this->vtpassService = $vtpassService;
    }

    public function listServices(): JsonResponse
    {
        $response = $this->vtpassService->getServiceCategories();

        if (!isset($response['code']) || $response['code'] !== '000') {
            return response()->json([
                'success' => false,
                'message' => $response['response_message'] ?? 'Failed to fetch services',
            ], 500);
        }

        $services = $response['content']['categories'] ?? [];

        return response()->json([
            'success' => true,
            'data' => collect($services)->map(function ($service) {
                return [
                    'id' => $service['ID'] ?? $service['id'] ?? null,
                    'name' => $service['name'] ?? null,
                    'slug' => $service['slug'] ?? null,
                ];
            })->values(),
        ]);
    }

    public function listProducts(Request $request, string $serviceId): JsonResponse
    {
        $response = $this->vtpassService->getServiceProducts($serviceId);

        if (!isset($response['code']) || $response['code'] !== '000') {
            return response()->json([
                'success' => false,
                'message' => $response['response_message'] ?? 'Failed to fetch products',
            ], 500);
        }

        $products = $response['content']['products'] ?? [];

        return response()->json([
            'success' => true,
            'data' => collect($products)->map(function ($product) {
                return [
                    'id' => $product['ID'] ?? $product['id'] ?? null,
                    'name' => $product['name'] ?? null,
                    'amount' => $product['amount'] ?? null,
                    'variant_id' => $product['variant_id'] ?? null,
                    'description' => $product['description'] ?? null,
                ];
            })->values(),
        ]);
    }
}
