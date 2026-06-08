<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    /**
     * Get the budget setting for the current (or specified) month.
     */
    public function show(Request $request): JsonResponse
    {
        $month = $request->query('month', now()->format('Y-m'));

        $budget = $request->user()
            ->budgetSettings()
            ->where('month', $month)
            ->first();

        return response()->json([
            'month' => $month,
            'monthly_limit' => $budget ? (float) $budget->monthly_limit : 150.00,
        ]);
    }

    /**
     * Create or update the budget limit for a month.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'nullable|string|regex:/^\d{4}-\d{2}$/',
            'monthly_limit' => 'required|numeric|min:1|max:99999',
        ]);

        $month = $validated['month'] ?? now()->format('Y-m');

        $budget = $request->user()
            ->budgetSettings()
            ->updateOrCreate(
                ['month' => $month],
                ['monthly_limit' => $validated['monthly_limit']],
            );

        return response()->json($budget);
    }
}
