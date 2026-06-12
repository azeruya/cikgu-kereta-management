<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\OnlineRequest;
use App\Models\Vehicle;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Sheets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OnlineRequestImportController extends Controller
{
    public function import(Request $request)
    {
        $user = $request->user();

        $rows = $this->readGoogleSheetRows();

        if (count($rows) <= 1) {
            return response()->json([
                'message' => 'No rows found in Google Sheet.',
                'imported' => 0,
                'skipped' => 0,
            ]);
        }

        $headers = array_map(fn ($h) => trim($h), $rows[0]);
        $dataRows = array_slice($rows, 1);

        $imported = 0;
        $skipped = 0;


        foreach ($dataRows as $rowIndex => $row) {
            $data = $this->mapRow($headers, $row);

        if (empty($data['name']) || empty($data['phone']) || empty($data['plate'])) {
            $skipped++;
            continue;
        }

        $hash = $this->makeRowHash($data);

        if (OnlineRequest::where('external_row_hash', $hash)->exists()) {
            $skipped++;
            continue;
        }

            DB::transaction(function () use ($data, $hash, $user, &$imported) {
                $customer = Customer::firstOrCreate(
                    [
                        'branch_id' => $user->branch_id,
                        'phone' => $this->cleanPhone($data['phone']),
                    ],
                    [
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'address' => null,
                    ]
                );

                if (!$customer->email && !empty($data['email'])) {
                    $customer->update(['email' => $data['email']]);
                }

                $vehicle = Vehicle::firstOrCreate(
                    [
                        'branch_id' => $user->branch_id,
                        'license_plate' => $this->cleanPlate($data['plate']),
                    ],
                    [
                        'customer_id' => $customer->id,
                        'make' => $data['brand'],
                        'model' => $data['model'],
                        'year' => $data['year'],
                    ]
                );

                if (!$vehicle->customer_id) {
                    $vehicle->update(['customer_id' => $customer->id]);
                }

                OnlineRequest::create([
                    'branch_id' => $user->branch_id,
                    'customer_id' => $customer->id,
                    'vehicle_id' => $vehicle->id,
                    'source' => 'google_form',
                    'external_row_hash' => $hash,
                    'submitted_at' => $data['submitted_at'],
                    'problem_description' => $data['problem'],
                    'terms_accepted' => $data['terms_accepted'] ? 'true' : 'false',
                    'status' => 'new',
                    'raw_data' => $data,
                ]);

                $imported++;
            });
        }

        return response()->json([
            'message' => "{$imported} online request(s) imported.",
            'imported' => $imported,
            'skipped' => $skipped,
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $requests = OnlineRequest::with([
                'customer:id,name,phone,email',
                'vehicle:id,license_plate,make,model,year',
            ])
            ->where('branch_id', $user->branch_id)
            ->latest()
            ->limit(10)
            ->get();

        return response()->json($requests);
    }

    public function convert(Request $request, $id)
    {
        $user = $request->user();

        $onlineRequest = OnlineRequest::where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->with(['customer', 'vehicle'])
            ->firstOrFail();

        if ($onlineRequest->status === 'converted') {
            return response()->json([
                'message' => 'This online request has already been converted.',
                'request' => $onlineRequest,
            ], 422);
        }

        return response()->json([
            'message' => 'Online request ready for conversion.',
            'request' => $onlineRequest->fresh(['customer', 'vehicle']),
            'redirect' => [
                'customer_id' => $onlineRequest->customer_id,
                'vehicle_id' => $onlineRequest->vehicle_id,
                'request_id' => $onlineRequest->id,
            ],
    ]);
}

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $onlineRequest = OnlineRequest::where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->with(['customer', 'vehicle'])
            ->firstOrFail();

        return response()->json($onlineRequest);
    }

    private function readGoogleSheetRows(): array
    {
        $client = new Client();
        $client->setApplicationName('Vulcan Auto Service');
        $client->setScopes([Sheets::SPREADSHEETS_READONLY]);

        /*
        * Production/Render:
        * Use full Google service account JSON from environment variable.
        *
        * Local development:
        * Fallback to JSON file path from GOOGLE_SHEETS_CREDENTIALS.
        */
        $serviceAccountJson = env('GOOGLE_SERVICE_ACCOUNT_JSON');

        if (!empty($serviceAccountJson)) {
            $credentials = json_decode($serviceAccountJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception(
                    'Invalid GOOGLE_SERVICE_ACCOUNT_JSON: ' . json_last_error_msg()
                );
            }

            $client->setAuthConfig($credentials);
        } else {
            $credentialsPath = base_path(env('GOOGLE_SHEETS_CREDENTIALS'));

            if (!file_exists($credentialsPath)) {
                throw new \Exception("Google Sheets credentials file not found at: {$credentialsPath}");
            }

            $client->setAuthConfig($credentialsPath);
        }

        $service = new Sheets($client);

        $spreadsheetId = env('GOOGLE_SHEET_ID');
        $range = env('GOOGLE_SHEET_RANGE', 'Form Responses 1!A:J');

        $response = $service->spreadsheets_values->get($spreadsheetId, $range);

        return $response->getValues() ?? [];
    }

    private function mapRow(array $headers, array $row): array
    {
        $record = [];

        foreach ($headers as $index => $header) {
            $record[$header] = $row[$index] ?? null;
        }

        return [
            'submitted_at' => $this->parseDate($record['Timestamp'] ?? null),
            'email' => $record['Email Address'] ?? null,
            'name' => $record["Customer's Name"] ?? null,
            'phone' => $record['Phone Number'] ?? null,
            'brand' => $record['Vehicle Brand'] ?? null,
            'model' => $record['Vehicle Model *Myvi, Bezza, Saga, Hilux, City, etc.'] ?? null,
            'year' => $record['Vehicle Year'] ?? null,
            'plate' => $record['Plate Number Example : JKJ8991 (NO SPACE)'] ?? null,
            'problem' => $record["Customer's Vehicle Problem"] ?? null,
            'terms_accepted' => !empty($record['Sila baca dan bersetuju dengan Terma & Syarat kami sebelum meneruskan. Please read and agree to our Terms & Conditions before continuing.']),
        ];
    }

    private function makeRowHash(array $data): string
    {
        return sha1(
            ($data['submitted_at']?->toDateTimeString() ?? '') . '|' .
            strtolower(trim($data['email'] ?? '')) . '|' .
            $this->cleanPhone($data['phone'] ?? '') . '|' .
            $this->cleanPlate($data['plate'] ?? '')
        );
    }

    private function cleanPhone(?string $phone): string
    {
        if (!$phone) {
            return '';
        }

        $phone = trim((string) $phone);

        // Remove spaces, dashes, brackets
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);

        // Remove leading +
        $phone = ltrim($phone, '+');

        // 0060193804822 -> 60193804822
        if (preg_match('/^00601\d{8,9}$/', $phone)) {
            return substr($phone, 2);
        }

        // Excel removed leading 0: 193804822 -> 60193804822
        if (preg_match('/^1\d{8,9}$/', $phone)) {
            return '60' . $phone;
        }

        // Local Malaysia: 0193804822 -> 60193804822
        if (preg_match('/^01\d{8,9}$/', $phone)) {
            return '6' . $phone;
        }

        // Already Malaysia international format
        if (preg_match('/^601\d{8,9}$/', $phone)) {
            return $phone;
        }

        // Fallback: digits only
        return preg_replace('/\D+/', '', $phone);
    }

    private function cleanPlate(?string $plate): string
    {
        return strtoupper(str_replace(' ', '', trim($plate ?? '')));
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (!$value) return null;

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}