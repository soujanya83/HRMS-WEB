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

    public function createNewEmployeeInXero($employee,  $connection)
    {
        try {
            // -----------------------------
            // 1. Decrypt Tokens
            // -----------------------------

            // Auto refresh token if needed
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


            // $accessToken = $connection->access_token;
            // $tenantId    = $connection->tenant_id;

            if (!$accessToken) {
                return [
                    'status' => false,
                    'message' => 'Access token missing'
                ];
            }
            // -----------------------------
            // 2. Prepare Address
            // -----------------------------
            $parsed = $this->parseAddress($employee->address);
            $calendarId = $this->getPayrollCalendarId($tenantId, $accessToken, $employee);

            // check employee exists on xero with e-mail or phone number

            // $existingXeroEmployee = EmployeeXeroConnection::where('employee_id', $employee->id)->first();
            // if ($existingXeroEmployee && $existingXeroEmployee->xero_employee_id) {
            //     return [
            //         'status' => false,
            //         'message' => 'Employee already exists on Xero.',
            //         'xero_employee_id' => $existingXeroEmployee->xero_employee_id
            //     ];
            // } else {
            //     // proceed to check if employee exists on xero with email or phone number
            //     $check = $this->checkEmployeesOnXero($employee);
            //     return $check;
            // }


            // -----------------------------
            // 3. Gender Mapping
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

            $gender = $genderMap[strtolower($employee->gender)] ?? 'N';

            // -----------------------------
            // 4. Fetch Earnings Rates Once
            // -----------------------------
            $earningsRates = $this->getEarningsRates($connection);

            $firstRateId   = $earningsRates['data'][0]['earningsRateID'] ?? null;
            $firstRateName = $earningsRates['data'][0]['name'] ?? null;
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


            $payload = [
                [
                    "Title"      => $employee->title ?? null,
                    "FirstName"  => $employee->first_name,
                    "LastName"   => $employee->last_name,
                    "Status"     => "ACTIVE",
                    "Email"      => $employee->email,

                    // Use the legacy /Date() format – your working Postman example requires it
                    "DateOfBirth" => "/Date(" . (strtotime($employee->dob) * 1000) . "+0000)/",
                    "StartDate"   => "/Date(" . (strtotime($employee->joining_date) * 1000) . "+0000)/",

                    "JobTitle" => $employee->job_title ?? "",
                    "Gender"   => $gender,

                    "EmploymentType" => "EMPLOYEE",
                    "IncomeType"     => "SALARYANDWAGES",

                    "OrdinaryEarningsRateID" => $firstRateId,
                    "PayrollCalendarID"      => $calendarId['calendar_id'] ?? null, // Ensure this is a valid GUID

                    "IsAuthorisedToApproveLeave"       => false,
                    "IsAuthorisedToApproveTimesheets"  => false, // Correct field name: Timesheets (plural)

                    "HomeAddress" => [
                        "AddressLine1" => $parsed['addressLine1'],
                        "AddressLine2" => $parsed['addressLine2'] ?? null,
                        "City"         => $parsed['city'],
                        "Region"       => "NSW", // Use valid AU state: NSW, VIC, QLD, SA, WA, TAS, ACT, NT
                        "PostalCode"   => $parsed['postalCode'] ?? "2131", // Provide real postcode if possible
                        "Country"      => "AUSTRALIA",
                    ],

                    "Phone"  => $employee->phone_number,
                    "Mobile" => $employee->phone_number,
                    "email" => $employee->personal_email,

                    "TaxDeclaration" => [
                        "EmploymentBasis"                    => $employmentBasis,
                        "AustralianResidentForTaxPurposes"   => true,
                        "TaxFreeThresholdClaimed"            => true,
                        "HasHELPDebt"                        => false,
                        "HasSFSSDebt"                        => false,
                        "HasStudentStartupLoan"              => false,
                        "HasTradeSupportLoanDebt"            => false,
                        "HasLoanOrStudentDebt"               => false,
                        "EligibleToReceiveLeaveLoading"      => false,
                        "ResidencyStatus"                    => "AUSTRALIANRESIDENT",
                    ],

                    "BankAccounts" => [
                        [
                            "StatementText" => "Weekly Pay",
                            "AccountName"   => $employee->bank_account_name ?? $employee->first_name . " " . $employee->last_name,
                            "BSB"           => $employee->bank_bsb ?? "000000",
                            "AccountNumber" => $employee->bank_account_number ?? "000000000",
                            "Remainder"     => true
                        ]
                    ],

                    "PayTemplate" => [
                        "EarningsLines" => [
                            [
                                "EarningsRateID"      => $firstRateId,
                                "CalculationType"     => "ENTEREARNINGSRATE",
                                "RatePerUnit"         => (float) ($earningperhour->base_salary ?? 0),
                                "NormalNumberOfUnits" => 1.00
                            ]
                        ],
                        "DeductionLines"     => [],
                        "SuperLines"         => [],
                        "ReimbursementLines" => [],
                        "LeaveLines"         => []
                    ],

                    "OpeningBalances" => [
                        "OpeningBalanceDate" => "/Date(" . (strtotime($employee->joining_date) * 1000) . "+0000)/",
                        "EarningsLines"      => [],
                        "PaidLeaveEarningsLines" => [],
                        "DeductionLines"     => [],
                        "Tax"                => 0.00,
                        "SuperLines"         => [],
                        "ReimbursementLines" => []
                    ]
                ]
            ];


            if ($employee->tax_file_number) {
                $payload[0]['TaxFileNumber'] = $employee->tax_file_number;
            }


            // -----------------------------
            // 6. Send to Xero
            // -----------------------------
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'xero-tenant-id' => $tenantId,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post('https://api.xero.com/payroll.xro/1.0/Employees', $payload);
            // dd($response->body());

            // Token expired
            // if ($response->status() === 401) {
            //     return [
            //         'status' => false,
            //         'message' => 'Token expired. Refresh token required.',
            //     ];
            // }

            $json = $response->json();
            // dd($json);

            // -----------------------------
            // 7. On Success → Save Mapping
            // -----------------------------
            if ($response->successful()) {
                $xeroEmployeeId = $json["Employees"][0]["EmployeeID"];
                $PayrollCalendarID = $json["Employees"][0]["PayrollCalendarID"];
                $OrdinaryEarningsRateID = $json['Employees'][0]['OrdinaryEarningsRateID'];
                $EarningsRateID = $json['Employees'][0]['PayTemplate']['EarningsLines']['EarningsRateID'];

                EmployeeXeroConnection::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'xero_connection_id' => $connection->id ?? "0",
                        'xerocalenderId' => $PayrollCalendarID,
                        'OrdinaryEarningsRateID' => $OrdinaryEarningsRateID,
                        'EarningsRateID' => $EarningsRateID ?? ""
                    ],
                    [
                        'organization_id' => $connection->organization_id,
                        'xero_employee_id' => $xeroEmployeeId,
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
            // 8. Save failure logs
            // -----------------------------
            EmployeeXeroConnection::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'xero_connection_id' => $connection->id,
                ],
                [
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
            return [
                'status' => false,
                'message' => 'Exception during sync',
                'error' => $e->getMessage()
            ];
        }
    }


    public function parseAddress($fullAddress)
    {
        // Split by comma
        $parts = array_map('trim', explode(',', $fullAddress));

        $addressLine1 = $parts[0] ?? null;
        $city         = $parts[1] ?? null;

        // Check if state + postal exist in 3rd part (AU / US format)
        $region = null;
        $postal = null;

        if (!empty($parts[2])) {
            // Try extracting "VIC 3000" or "NSW 2000"
            if (preg_match('/([A-Za-z]{2,4})\s*(\d{4,6})?/', $parts[2], $matches)) {
                $region = $matches[1] ?? null;
                $postal = $matches[2] ?? null;
            }
        }

        $country = $parts[3] ?? ($parts[2] ?? "Australia");

        return [
            "addressLine1" => $addressLine1,
            "city"         => $city,
            "region"       => $region,
            "postalCode"   => $postal,
            "country"      => $country
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
