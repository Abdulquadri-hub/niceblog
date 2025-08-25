<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class Controller
{
    public static function successResponse($data = [], string $message = "Success", int $statusCode = 200): JsonResponse {
        return response()->json([
            'success' =>  true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    public static function errorResponse(string $message = "Error", int $statusCode = 400, array $errors = []): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }
}
