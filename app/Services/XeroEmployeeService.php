<?php

namespace App\Services;

use App\Models\XeroConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Services\XeroRefreshAccessTokenServices;
use App\Models\EmployeeXeroConnection;
use App\Models\XeroPayslip;
use App\Models\XeroTimesheet;
use App\Models\Employee\Employee;
use App\Models\EmploymentType;
use App\Models\SalaryStructure;
use App\Services\Xero\XeroTokenService;
use App\Services\Xero\XeroApiService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class XeroEmployeeService
{
    /**
     * Sync new employee to Xero.
     */

    public function syncEmployee(Employee $employee, XeroConnection $connection)
    {

        $existingXeroEmployee = EmployeeXeroConnection::where('employee_id', $employee->id)->first();
        // 1. Already linked to Xero → return success, do NOT treat as error
        if ($existingXeroEmployee && $existingXeroEmployee->xero_employee_id) {
            return [
                'status' => true,
                'message' => 'Employee already linked with Xero.',
                'xero_employee_id' => $existingXeroEmployee->xero_employee_id
            ];
        }

        // 2. Try matching on Xero by email/phone
        $check = $this->checkEmployeesOnXero($employee);

        // 2A. Found match → return it, let caller link to Xero
        if ($check['status'] === true && ($check['code'] ?? null) === 'matched') {
            return $check;
        }

        // 2B. Conflict: Xero employee linked to someone else → stop
        if (($check['code'] ?? null) === 'linked_to_another') {
            return $check;
        }

        // 2C. No match found → trigger CREATE NEW XERO EMPLOYEE
        if (($check['code'] ?? null) === 'not_found') {
            return $this->createNewEmployeeInXero($employee, $connection);
        }

        // Default return
        return $check;
    }

 public function createNewEmployeeInXero($employee, $connection)
{
    try {
        // -----------------------------
        // 1. Refresh Token if Needed
        // -----------------------------
        Log::info('Before refresh', [
            'token_last_6' => substr($connection->access_token, -6),
            'expires_at' => $connection->token_expires_at
        ]);

        $connection = app(XeroTokenService::class)->refreshIfNeeded($connection);
        $connection = $connection->fresh();

        Log::info('After refresh', [
            'token_last_6' => substr($connection->access_token, -6),
            'expires_at' => $connection->token_expires_at
        ]);

        $accessToken = $connection->access_token;
        $tenantId = $connection->tenant_id;

        if (!$accessToken) {
            return [
                'status' => false,
                'message' => 'Access token missing'
            ];
        }

        // -----------------------------
        // 2. Validate Required Fields
        // -----------------------------
        $requiredFields = [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => $employee->email,
            'dob' => $employee->dob,
            'joining_date' => $employee->joining_date,
        ];

        foreach ($requiredFields as $field => $value) {
            if (empty($value)) {
                return [
                    'status' => false,
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required for Xero sync'
                ];
            }
        }

        // -----------------------------
        // 3. Prepare Address
        // -----------------------------
        $parsed = $this->parseAddress($employee->address);
        
        // Validate city is not empty
        if (empty($parsed['city'])) {
            return [
                'status' => false,
                'message' => 'City/Suburb is required in address'
            ];
        }

        // -----------------------------
        // 4. Get Payroll Calendar
        // -----------------------------
        $calendarId = $this->getPayrollCalendarId($tenantId, $accessToken, $employee);
        
        if (empty($calendarId['calendar_id'])) {
            return [
                'status' => false,
                'message' => 'No Payroll Calendar found. Please set up a Payroll Calendar in Xero first.'
            ];
        }

        // -----------------------------
        // 5. Gender Mapping
        // -----------------------------
        $genderMap = [
            'male' => 'M',
            'm' => 'M',
            'female' => 'F',
            'f' => 'F',
            'non-binary' => 'I',
            'nonbinary' => 'I',
            'nb' => 'I',
        ];

        $gender = $genderMap[strtolower($employee->gender ?? 'm')] ?? 'N';

        // -----------------------------
        // 6. Fetch Earnings Rates
        // -----------------------------
        $earningsRates = $this->getEarningsRates($connection);

        $firstRateId = $earningsRates['data'][0]['earningsRateID'] ?? null;
        
        if (!$firstRateId) {
            return [
                'status' => false,
                'message' => 'No Earnings Rate found in Xero. Please set up Earnings Rates first.'
            ];
        }

        // -----------------------------
        // 7. Employment Basis Mapping
        // -----------------------------
        $getemplyeementbasisId = $employee->employment_type;
        $getemplyeementbasisName = EmploymentType::where('id', $getemplyeementbasisId)->value('name');
        
        if ($getemplyeementbasisName == 'full-time') {
            $employmentBasis = "FULLTIME";
        } elseif ($getemplyeementbasisName == 'Part Time') {
            $employmentBasis = "PARTTIME";
        } elseif ($getemplyeementbasisName == 'Casual') {
            $employmentBasis = "CASUAL";
        } else {
            $employmentBasis = "FULLTIME";
        }

        $earningperhour = SalaryStructure::where('employee_id', $employee->id)->first();

        // -----------------------------
        // 8. Build Payload
        // -----------------------------
        $payload = [
            [
                "Title" => $employee->employee_code ?? null,
                "FirstName" => $employee->first_name,
                "LastName" => $employee->last_name,
                "Status" => "ACTIVE",
                "Email" => $employee->personal_email,

                "DateOfBirth" => "/Date(" . (strtotime($employee->date_of_birth) * 1000) . "+0000)/",
                "StartDate" => "/Date(" . (strtotime($employee->joining_date) * 1000) . "+0000)/",

                "JobTitle" => $employee->designation->title ?? "",
                "Gender" => $gender,

                "EmploymentType" => "EMPLOYEE",
                "IncomeType" => "SALARYANDWAGES",

                "OrdinaryEarningsRateID" => $firstRateId,
                "PayrollCalendarID" => $calendarId['calendar_id'],

                "IsAuthorisedToApproveLeave" => false,
                "IsAuthorisedToApproveTimesheets" => false,

                "HomeAddress" => [
                    "AddressLine1" => $parsed['addressLine1'] ?: 'N/A',
                    "AddressLine2" => $parsed['addressLine2'] ?? null,
                    "City" => $parsed['city'], // ✅ Required field
                    "Region" => $parsed['region'] ?: "NSW",
                    "PostalCode" => $parsed['postalCode'] ?: "2000",
                    "Country" => "AUSTRALIA",
                ],

                "Phone" => $employee->phone_number ?? null,
                "Mobile" => $employee->phone_number ?? null,

                "TaxDeclaration" => [
                    "EmploymentBasis" => $employmentBasis,
                    "AustralianResidentForTaxPurposes" => true,
                    "TaxFreeThresholdClaimed" => true,
                    "HasHELPDebt" => false,
                    "HasSFSSDebt" => false,
                    "HasStudentStartupLoan" => false,
                    "HasTradeSupportLoanDebt" => false,
                    "HasLoanOrStudentDebt" => false,
                    "EligibleToReceiveLeaveLoading" => false,
                    "ResidencyStatus" => "AUSTRALIANRESIDENT",
                ],

                "BankAccounts" => [
                    [
                        "StatementText" => "Weekly Pay",
                        "AccountName" => $employee->bank_account_name ?? $employee->first_name . " " . $employee->last_name,
                        "BSB" => $employee->bank_bsb ?? "000000",
                        "AccountNumber" => $employee->bank_account_number ?? "000000000",
                        "Remainder" => true
                    ]
                ],

                "PayTemplate" => [
                    "EarningsLines" => [
                        [
                            "EarningsRateID" => $firstRateId,
                            "CalculationType" => "ENTEREARNINGSRATE",
                            "RatePerUnit" => (float) ($earningperhour->base_salary ?? 0),
                            "NormalNumberOfUnits" => 1.00
                        ]
                    ],
                    "DeductionLines" => [],
                    "SuperLines" => [],
                    "ReimbursementLines" => [],
                    "LeaveLines" => []
                ],

                "OpeningBalances" => [
                    "OpeningBalanceDate" => "/Date(" . (strtotime($employee->joining_date) * 1000) . "+0000)/",
                    "EarningsLines" => [],
                    "PaidLeaveEarningsLines" => [],
                    "DeductionLines" => [],
                    "Tax" => 0.00,
                    "SuperLines" => [],
                    "ReimbursementLines" => []
                ]
            ]
        ];

        // Add Tax File Number if exists
        if ($employee->tax_file_number) {
            $payload[0]['TaxFileNumber'] = $employee->tax_file_number;
        }

        // -----------------------------
        // 9. Send to Xero
        // -----------------------------
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'xero-tenant-id' => $tenantId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post('https://api.xero.com/payroll.xro/1.0/Employees', $payload);

        $json = $response->json();

        // Log response for debugging
        Log::info('Xero Employee Sync Response', [
            'status' => $response->status(),
            'response' => $json
        ]);

        // -----------------------------
        // 10. Handle Success
        // -----------------------------
        if ($response->successful()) {
            $xeroEmployeeId = $json["Employees"][0]["EmployeeID"];
            $PayrollCalendarID = $json["Employees"][0]["PayrollCalendarID"];
            $OrdinaryEarningsRateID = $json['Employees'][0]['OrdinaryEarningsRateID'];
            $EarningsRateID = $json['Employees'][0]['PayTemplate']['EarningsLines'][0]['EarningsRateID'] ?? null;

            EmployeeXeroConnection::updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'xero_connection_id' => $connection->id,
                    'organization_id' => $connection->organization_id,
                    'xero_employee_id' => $xeroEmployeeId,
                    'xerocalenderId' => $PayrollCalendarID,
                    'OrdinaryEarningsRateID' => $OrdinaryEarningsRateID,
                    'EarningsRateID' => $EarningsRateID ?? "",
                    'is_synced' => 1,
                    'last_synced_at' => now(),
                    'sync_status' => 'success',
                    'sync_error' => null,
                    'xero_data' => json_encode($json),
                ]
            );

            return [
                'status' => true,
                'message' => 'Employee synced successfully!',
                'xero_employee_id' => $xeroEmployeeId
            ];
        }

        // -----------------------------
        // 11. Handle Failure
        // -----------------------------
        EmployeeXeroConnection::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'xero_connection_id' => $connection->id,
                'organization_id' => $connection->organization_id,
                'is_synced' => 0,
                'sync_status' => 'failed',
                'sync_error' => json_encode($json),
                'last_synced_at' => now(),
            ]
        );

        return [
            'status' => false,
            'message' => 'Sync failed',
            'response' => $json
        ];

    } catch (\Exception $e) {
        Log::error('Xero Employee Sync Exception', [
            'employee_id' => $employee->id ?? null,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return [
            'status' => false,
            'message' => 'Exception during sync',
            'error' => $e->getMessage()
        ];
    }
}

public function parseAddress($fullAddress)
{
    if (empty($fullAddress)) {
        return [
            "addressLine1" => "N/A",
            "addressLine2" => null,
            "city" => "Sydney",  // ✅ Default city
            "region" => "NSW",
            "postalCode" => "2000",
            "country" => "Australia"
        ];
    }

    // Split by comma
    $parts = array_map('trim', explode(',', $fullAddress));

    $addressLine1 = $parts[0] ?? 'N/A';
    $city = $parts[1] ?? 'Sydney';  // ✅ Default if missing

    // Extract state + postal code
    $region = null;
    $postal = null;

    if (!empty($parts[2])) {
        if (preg_match('/([A-Za-z]{2,4})\s*(\d{4,6})?/', $parts[2], $matches)) {
            $region = $matches[1] ?? null;
            $postal = $matches[2] ?? null;
        }
    }

    $country = $parts[3] ?? "Australia";

    return [
        "addressLine1" => $addressLine1,
        "addressLine2" => $parts[2] ?? null,
        "city" => $city,  // ✅ Always has value
        "region" => $region ?: "NSW",
        "postalCode" => $postal ?: "2000",
        "country" => $country
    ];
}

    public function getEarningsRates(XeroConnection $connection)
    {
        // 1. Decrypt tokens
        $accessToken = $connection->access_token;
        $tenantId    = $connection->tenant_id;

        if (!$accessToken) {
            return [
                'status' => false,
                'message' => 'Access token missing'
            ];
        }

        // 2. Send request to Xero
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'xero-tenant-id' => $tenantId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->get('https://api.xero.com/payroll.xro/2.0/EarningsRates');

        // 3. Token expired?
        if ($response->status() === 401) {
            return [
                'status' => false,
                'message' => 'Token expired. Refresh token required.'
            ];
        }

        // 4. If Xero returned success
        if ($response->successful()) {
            $data = $response->json();
            // dd($data);

            return [
                'status' => true,
                'data' => $data['earningsRates'] ?? []
            ];
        }

        return [
            'status' => false,
            'message' => 'Failed to fetch earnings rates',
            'response' => $response->json()
        ];
    }

    public function getPayrollCalendarId($tenantId, $accessToken, $employee)
    {
        $url = "https://api.xero.com/payroll.xro/1.0/PayrollCalendars";

        try {
            $response = Http::withHeaders([
                "Authorization" => "Bearer $accessToken",
                "xero-tenant-id" => $tenantId,
                "Accept" => "application/json"
            ])->get($url);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'message' => 'Failed to fetch payroll calendars',
                    'status'  => $response->status(),
                    'error'   => $response->body(),
                ];
            }

            $data = $response->json();

            // No calendars found
            if (!isset($data['PayrollCalendars']) || count($data['PayrollCalendars']) === 0) {
                return [
                    'success' => false,
                    'message' => 'No payroll calendars found.'
                ];
            }

            // Get first Payroll Calendar ID
            if ($employee->payfrequency == 'weekly') {
                $calendarId = $data['PayrollCalendars'][0]['PayrollCalendarID'];
            } elseif ($employee->payfrequency == 'Fortnightly') {
                $calendarId = $data['PayrollCalendars'][1]['PayrollCalendarID'];
            } elseif ($employee->payfrequency == 'Monthly') {
                $calendarId = $data['PayrollCalendars'][2]['PayrollCalendarID'];
            } else {
                $calendarId = $data['PayrollCalendars'][0]['PayrollCalendarID'];
            }

            return [
                'success' => true,
                'calendar_id' => $calendarId,
                'data' => $data['PayrollCalendars']
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function checkEmployeesOnXero($employee)
    {
        try {
            $email = strtolower($employee->personal_email);
            $phone = preg_replace('/\D/', '', $employee->phone_number);

            // dd($email, $phone);

            // ----------------------------------------
            // 1. Get active Xero connection
            // ----------------------------------------
            $xeroConnection = XeroConnection::where('organization_id', $employee->organization_id)
                ->where('is_active', 1)
                ->first();

            if (!$xeroConnection) {
                return [
                    'status' => false,
                    'message' => 'No active Xero connection found.',
                    'code' => 'not_found',
                ];
            }

            // ----------------------------------------
            // 2. Fetch all Xero employees
            // ----------------------------------------
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $xeroConnection->access_token,
                'xero-tenant-id' => $xeroConnection->tenant_id,
                'Accept' => 'application/json'
            ])->get('https://api.xero.com/payroll.xro/1.0/Employees');

            $xeroEmployees = $response->json()['Employees'] ?? [];

            foreach ($xeroEmployees as $xe) {

                // Normalize fields
                $xeroEmail = strtolower($xe['Email'] ?? '');
                $xeroPhone = preg_replace('/\D/', '', ($xe['Phone'] ?? ''));

                // ----------------------------------------------------
                // Skip employees that have no email AND no phone
                // ----------------------------------------------------
                if (empty($xeroEmail) && empty($xeroPhone)) {
                    continue;
                }


                // ----------------------------------------------------
                // EMAIL MATCH
                // ----------------------------------------------------
                if (!empty($email) && !empty($xeroEmail)) {
                    //    dd($email, $xeroEmail);

                    if (trim(strtolower($email)) === trim(strtolower($xeroEmail))) {
                        return $this->linkEmployee($employee, $xe, $xeroConnection);
                    }
                }

                // ----------------------------------------------------
                // PHONE MATCH
                // ----------------------------------------------------
                if (!empty($xeroPhone) && !empty($phone)) {

                    $normalizedPhone = ltrim($phone, '+');
                    $normalizedXeroPhone = ltrim($xeroPhone, '+');

                    if (
                        $normalizedXeroPhone === $normalizedPhone ||              // exact match
                        $normalizedXeroPhone === ltrim($normalizedPhone, '0') ||  // 0400 -> 400
                        $normalizedXeroPhone === "61" . ltrim($normalizedPhone, '0')
                    ) {
                        return $this->linkEmployee($employee, $xe, $xeroConnection);
                    }
                }
            }

            // ----------------------------------------------------
            // NO MATCH FOUND
            // ----------------------------------------------------
            return [
                'status' => false,
                'message' => 'No matching employee found in Xero.',
                'code' => 'not_found'

            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => 'Exception during sync',
                'error' => $e->getMessage()
            ];
        }
    }



    private function linkEmployee($employee, $xe, $xeroConnection)
    {
        // dd($employee, $xe, $xeroConnection);
        // check duplicate xero mapping
        $existing = EmployeeXeroConnection::where('xero_employee_id', $xe['EmployeeID'])->first();

        if ($existing && $existing->employee_id != $employee->id) {
            return [
                'status' => false,
                'message' => 'This Xero employee is already linked to another employee.',
                'xero_employee' => $xe,
                'linked_to' => $existing,
                'code' => 'linked_to_another'
            ];
        }

        $connection = EmployeeXeroConnection::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'organization_id' => $employee->organization_id,
                'xero_connection_id' => $xeroConnection->id,
            ],
            [
                'xero_employee_id' => $xe['EmployeeID'],
                'xero_data' => json_encode($xe),
                'is_synced' => 1,
                'sync_status' => 'success',
            ]
        );

        return [
            'status' => true,
            'message' => 'Employee matched and linked with Xero.',
            'employee' => $xe,
            'link' => $connection,
            'code' => 'MatchedLinked',
        ];
    }
}
