<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** Bentuk respons JSON standar: { data, meta }. */
    protected function ok(mixed $data, int $status = 200, array $headers = [])
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'season' => app(\App\Support\GameConfig::class)->currentSeasonNumber(),
                'server_time' => now()->toIso8601String(),
            ],
        ], $status, $headers);
    }

    protected function fail(string $code, string $message, int $status = 422)
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
