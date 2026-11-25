<?php

namespace App\Helpers;

use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\View;

class Reply
{
    public static function success(string $message): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => $message,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function successWith(string $message, array $data): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function successWithView(string $view, array $data = []): JsonResponse
    {
        /** @var ViewContract $rendered */
        $rendered = View::make($view, $data);

        return response()->json([
            'status' => true,
            'html' => $rendered->render(),
        ]);
    }

    public static function error(string $message): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => $message,
        ], 500);
    }
}
