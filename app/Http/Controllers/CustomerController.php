<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Customer::query()
            ->select([
                'id',
                'branch_id',
                'name',
                'phone',
                'email',
                'address',
                'created_at',
            ])
            ->where('branch_id', $user->branch_id)
            ->with([
                'vehicles:id,customer_id,license_plate',
                'latestTransaction',
                'latestTransaction.vehicle:id,license_plate',
            ]);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhereHas('vehicles', function ($vehicleQuery) use ($search) {
                        $vehicleQuery->where('license_plate', 'ilike', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'active') {
                $query->whereHas('latestTransaction', function ($q) {
                    $q->where('transactions.status', 'invoice');
                });
            }

            if ($request->status === 'inactive') {
                $query->where(function ($q) {
                    $q->whereDoesntHave('latestTransaction')
                        ->orWhereHas('latestTransaction', function ($trx) {
                            $trx->where('transactions.status', '!=', 'invoice');
                        });
                });
            }
        }

        $customers = $query
            ->orderByDesc('id')
            ->paginate(10);

        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->where(function ($query) use ($user) {
                    return $query->where('branch_id', $user->branch_id);
                }),
            ],
            'address' => 'nullable|string',
        ]);

        $customer = Customer::create([
            'name' => $validated['name'],
            'phone' => $this->normalizeMalaysiaPhone($validated['phone'] ?? null),
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'branch_id' => $user->branch_id,
        ]);

        return response()->json($customer, 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $customer = Customer::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->with([
                'vehicles:id,customer_id,license_plate,make,model,year',
                'transactions' => function ($query) {
                    $query->select(
                            'id',
                            'customer_id',
                            'vehicle_id',
                            'document_number',
                            'status',
                            'total_amount',
                            'discount_amount',
                            'created_at'
                        )
                        ->with([
                            'vehicle:id,license_plate,make,model,year'
                        ])
                        ->orderByDesc('id')
                        ->limit(5);
                }
            ])
            ->withCount('transactions')
            ->withSum('transactions', 'total_amount')
            ->firstOrFail();

        return response()->json($customer);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        $customer = Customer::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')
                    ->ignore($customer->id)
                    ->where(function ($query) use ($user) {
                        return $query->where('branch_id', $user->branch_id);
                    }),
            ],
            'address' => 'nullable|string',
        ]);

        if (array_key_exists('phone', $validated)) {
            $validated['phone'] = $this->normalizeMalaysiaPhone($validated['phone']);
        }

        $customer->update($validated);

        return response()->json($customer);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();

        $query = Customer::query()
            ->where('branch_id', $user->branch_id)
            ->with([
                'vehicles:id,customer_id,license_plate,make,model,year',
                'transactions:id,customer_id,document_number,status,total_amount,created_at',
            ])
            ->withCount('transactions')
            ->withSum('transactions', 'total_amount');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhereHas('vehicles', function ($vehicleQuery) use ($search) {
                        $vehicleQuery->where('license_plate', 'ilike', "%{$search}%")
                            ->orWhere('make', 'ilike', "%{$search}%")
                            ->orWhere('model', 'ilike', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'active') {
                $query->whereHas('transactions', function ($q) {
                    $q->where('status', 'invoice');
                });
            }

            if ($request->status === 'inactive') {
                $query->whereDoesntHave('transactions', function ($q) {
                    $q->where('status', 'invoice');
                });
            }
        }

        $filename = 'customers-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fwrite($handle, "sep=,\n");

            fputcsv($handle, [
                'Name',
                'Phone',
                'Email',
                'Address',
                'Vehicles',
                'Total Visits',
                'Total Spent',
                'Latest Transaction',
                'Latest Status',
                'Created At',
            ]);

            $query->orderBy('name')
                ->chunk(200, function ($customers) use ($handle) {
                    foreach ($customers as $customer) {
                        $vehicles = $customer->vehicles
                            ->map(function ($vehicle) {
                                return trim(
                                    ($vehicle->license_plate ?? '-') . ' - ' .
                                    ($vehicle->make ?? '') . ' ' .
                                    ($vehicle->model ?? '') . ' ' .
                                    ($vehicle->year ?? '')
                                );
                            })
                            ->implode(' | ');

                        $latestTransaction = $customer->transactions
                            ->sortByDesc('created_at')
                            ->first();

                        fputcsv($handle, [
                            $customer->name,
                            $this->csvText($customer->phone),
                            $customer->email ?? '',
                            $customer->address ?? '',
                            $vehicles ?: 'No vehicles',
                            $customer->transactions_count ?? 0,
                            number_format((float) ($customer->transactions_sum_total_amount ?? 0), 2, '.', ''),
                            $this->csvText($latestTransaction?->document_number),
                            $latestTransaction?->status ?? '',
                            $this->csvText(optional($customer->created_at)->format('d/m/Y H:i')),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $customer = Customer::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->firstOrFail();

        $customer->delete();

        return response()->json(['message' => 'Customer deleted']);
    }

    private function csvText($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return "\t" . (string) $value;
    }

    private function normalizeMalaysiaPhone($phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $phone = trim((string) $phone);
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
        $phone = ltrim($phone, '+');

        // Excel removed leading 0: 193804822 -> 0193804822
        if (preg_match('/^1\d{8,9}$/', $phone)) {
            $phone = '0' . $phone;
        }

        // Local Malaysia: 0193804822 -> 60193804822
        if (preg_match('/^01\d{8,9}$/', $phone)) {
            $phone = '6' . $phone;
        }

        // Already Malaysia international format
        if (preg_match('/^601\d{8,9}$/', $phone)) {
            return $phone;
        }

        // 0060193804822 -> 60193804822
        if (preg_match('/^00601\d{8,9}$/', $phone)) {
            return substr($phone, 2);
        }

        return $phone;
    }
}