<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PublicController extends Controller
{
    public function plans(): JsonResponse
    {
        $plans = DB::table('plans')
            ->whereNull('tenant_id')
            ->where('is_active', true)
            ->orderBy('monthly_price')
            ->get([
                'id',
                'code',
                'name',
                'description',
                'monthly_price',
                'yearly_price',
            ]);

        return response()->json([
            'plans' => $plans,
        ]);
    }
}
