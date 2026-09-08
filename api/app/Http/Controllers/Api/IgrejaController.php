<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Igreja;
use Illuminate\Http\JsonResponse;

class IgrejaController extends Controller
{
    public function index(): JsonResponse
    {
        $igrejas = Igreja::query()
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return response()->json(['data' => $igrejas]);
    }
}
