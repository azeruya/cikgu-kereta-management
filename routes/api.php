<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ExpenseController;

// PUBLIC routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// PROTECTED routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('vehicles', VehicleController::class);

    // part custom routes must come BEFORE apiResource('parts')
    Route::get('parts/low-stock', [PartController::class, 'lowStock']);
    Route::get('parts/compatible/{vehicleId}', [PartController::class, 'compatibleParts']);
    Route::apiResource('parts', PartController::class);

    // transactions
    Route::get('transactions', [TransactionController::class, 'index']);
    Route::get('transactions/{id}', [TransactionController::class, 'show']);
    Route::post('transactions', [TransactionController::class, 'store']);
    Route::post('transactions/{id}/confirm', [TransactionController::class, 'confirmInvoice']);
    Route::post('transactions/{id}/pay', [TransactionController::class, 'markPaid']);

    // expenses
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::get('/expenses/{id}', [ExpenseController::class, 'show']);
    Route::put('/expenses/{id}', [ExpenseController::class, 'update']);
    Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);
    Route::get('expenses/export/csv', [ExpenseController::class, 'exportCsv']);
    // only keep this if the method exists and you still need it
    // Route::get('job-orders', [TransactionController::class, 'jobOrders']);
});

// ADMIN routes
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/admin-test', function () {
        return response()->json(['message' => 'Hello Admin']);
    });
});