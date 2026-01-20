<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee\Employee;
use App\Models\SalaryStructure;
use App\Models\Employee\Attendance;
use App\Models\Employee\Leave as EmployeeLeave;
use App\Models\OrganizationLeave;
use App\Models\Payrolls as Payroll;
use App\Models\TaxSlabs as TaxSlab;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use App\Models\Organization;
use App\Models\SalaryStructureComponents;
use App\Models\SalaryRevisions;
use App\Models\Employee\OvertimeRequest;
use App\Models\Employee\Timesheet;
use Illuminate\Support\Facades\Storage;


class PayrollsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // at top of your command class
    protected $signature = 'payroll:generate-organization
                        {--month= : Month in Y-m format (optional)}
                        {--org= : organization id (optional)}
                        {--employee= : employee id (optional)}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new payroll entry for an employee for a given month';



    public function handle()
    {
        $month = $this->option('month') ?? now()->subMonth()->format('Y-m'); // default: previous month
        $targetOrgs = $this->option('org')
            ? Organization::where('id', $this->option('org'))->get()
            : Organization::all();

        $this->info("🚀 Generating payroll for month: {$month}");

        foreach ($targetOrgs as $org) {
            $this->info("🏢 Processing Organization: {$org->name} (ID: {$org->id})");

            $employees = Employee::with('salaryStructure')->where('organization_id', $org->id)
                ->get();

            if ($employees->isEmpty()) {
                $this->warn("⚠️  No employees found for organization {$org->name}");
                continue;
            }
            // dd($employees);
            foreach ($employees as $employee) {
                try {
                    DB::beginTransaction();

                    // Skip if payroll already exists
                    $fromDate = Carbon::parse($month)->startOfMonth()->toDateString();
                    $toDate   = Carbon::parse($month)->endOfMonth()->toDateString();

                    $exists = Payroll::where('employee_id', $employee->id)
                        ->where(function ($q) use ($fromDate, $toDate) {
                            $q->where(function ($q1) use ($fromDate, $toDate) {
                                // Existing payroll fully covers the target month
                                $q1->where('from_date', '<=', $fromDate)
                                    ->where('to_date', '>=', $toDate);
                            })
                                ->orWhere(function ($q2) use ($fromDate, $toDate) {
                                    // Overlap on start
                                    $q2->whereBetween('from_date', [$fromDate, $toDate]);
                                })
                                ->orWhere(function ($q3) use ($fromDate, $toDate) {
                                    // Overlap on end
                                    $q3->whereBetween('to_date', [$fromDate, $toDate]);
                                });
                        })
                        ->exists();

                    if ($exists) {
                        $this->warn("⏩ Payroll already exists for Employee ID {$employee->id} ({$month})");
                        DB::rollBack();
                        return;
                    }


                    $this->generatePayrollForEmployee($employee, $org->id, $month, $toDate, $fromDate);

                    DB::commit();
                    $this->info("✅ Payroll generated for Employee: {$employee->id}");
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->error("❌ Error for Employee {$employee->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("🎉 Payroll generation completed for all organizations (Month: {$month})");
    }

    /**
     * Generate payroll for a single employee
     */
    private function generatePayrollForEmployee($employee, $organizationId, $month, $toDate, $fromDate)
    {

        $monthCarbon = Carbon::parse($month);
        $totalDays = $monthCarbon->daysInMonth;

        // ✅ Attendance Records for the Month
        $attendanceRecords = Attendance::where('employee_id', $employee->id)
            ->whereMonth('date', $monthCarbon->month)
            ->get();

        // ✅ Attendance Counts
        $presents   = $attendanceRecords->where('status', 'present')->count();
        $halfDays   = $attendanceRecords->where('status', 'half_day')->count();
        $absents    = $attendanceRecords->where('status', 'absent')->count();
        $onLeaveIds = $attendanceRecords->where('status', 'on_leave')->pluck('id');

        // ✅ Overtime Calculation
        $overtimeIds = $attendanceRecords->where('is_overtime', 1)->pluck('id');
        $overtimeHours = 0;
        // dd($overtimeIds);

        if ($overtimeIds->isNotEmpty()) {
            $overtimeHours = OvertimeRequest::whereIn('attendance_id', $overtimeIds)
                ->where('status', 'hr_approved')
                ->sum('actual_overtime_hours');
        }

        // ✅ Define month range
        $monthStart = $monthCarbon->copy()->startOfMonth();
        $monthEnd = $monthCarbon->copy()->endOfMonth();

        // ✅ Fetch approved leaves overlapping this month
        $leaves = EmployeeLeave::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('start_date', [$monthStart, $monthEnd])
                    ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                    ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                        $q2->where('start_date', '<=', $monthStart)
                            ->where('end_date', '>=', $monthEnd);
                    });
            })
            ->get();

        $orgLeaves = OrganizationLeave::where('organization_id', $organizationId)
            ->where('is_active', 1)
            ->get()
            // key by lowercased leave_type to allow safe lookups
            ->keyBy(function ($item) {
                return strtolower($item->leave_type);
            });

        $paidLeaveDays = 0;
        $unpaidDays = 0;
        foreach ($leaves as $lv) {
            // compute overlap with the payroll month accurately
            $lvStart = Carbon::parse($lv->start_date);
            $lvEnd = Carbon::parse($lv->end_date);

            $start = $lvStart->greaterThan($monthStart) ? $lvStart : $monthStart;
            $end = $lvEnd->lessThan($monthEnd) ? $lvEnd : $monthEnd;

            // if end falls before start due to unexpected dates, skip
            if ($end->lt($start)) {
                continue;
            }

            $days = $start->diffInDays($end) + 1;

            // use lowercased key lookup safely via Collection::get()
            $leaveKey = strtolower($lv->leave_type ?? '');
            $policy = $orgLeaves->get($leaveKey);

            if ($policy && $policy->paid) {
                // compute previously used days for this leave type before the month start
                $usedBefore = EmployeeLeave::where('employee_id', $employee->id)
                    ->where('leave_type', $lv->leave_type)
                    ->where('status', 'approved')
                    ->whereDate('end_date', '<', $monthStart)
                    ->get()
                    ->sum(function ($l) {
                        return Carbon::parse($l->start_date)->diffInDays(Carbon::parse($l->end_date)) + 1;
                    });

                $granted = (int) ($policy->granted_days ?? 0);
                $remaining = max(0, $granted - $usedBefore);

                $paidAllocated = min($days, $remaining);
                $paidLeaveDays += $paidAllocated;
                $unpaidDays += ($days - $paidAllocated);
            } else {
                // no policy or unpaid leave type; count as unpaid
                $unpaidDays += $days;
            }
        }



        // ✅ Fetch salary structure with components & their types
        $structure = SalaryStructure::with('components.componentType')
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->firstOrFail();

        // ✅ Compute Working and Effective Days
        $workingDays = $presents + ($halfDays * 0.5);
        $effectiveDays = $workingDays + $paidLeaveDays - $unpaidDays;

        // ✅ Salary Computation
        $base = $structure->base_salary ?? 0;
        $earnings = 0;
        $deductions = 0;

        // Base prorated earnings
        $earnings += round(($base / $totalDays) * $effectiveDays, 2);

        foreach ($structure->components as $comp) {
            // If is_custom = true → direct amount, else → percentage of base
            if ($comp->is_custom) {
                $amt = round(($comp->amount / $totalDays) * $effectiveDays, 2);
            } else {
                $amt = round((($comp->percentage / 100) * $base / $totalDays) * $effectiveDays, 2);
            }

            if ($comp->componentType->category === 'earning' || $comp->componentType->category === 'benefit') {
                $earnings +=   $base * $comp->percentage / 100;
            } else {
                $deductions += $base * $comp->percentage / 100;
            }
        }

        // ✅ Overtime Pay (Base salary hourly)
        $overtimePay = round(($base / 160) * $overtimeHours, 2);
        $earnings += $overtimePay;

        // ✅ Tax Deduction
        $tax = $this->calculateTax($earnings * 12, $organizationId, 'new') / 12;

        // ✅ Deduct unpaid leaves proportionally (record separately for payslip)
        $unpaidLeaveDeduction = 0;
        if ($unpaidDays > 0) {
            $unpaidLeaveDeduction = round(($earnings / $totalDays) * $unpaidDays, 2);
            $deductions += $unpaidLeaveDeduction;
        }

        // ✅ Final Net Salary
        $net = $earnings - ($deductions + $tax);

        // ✅ Build Component Breakdown for payslip
        $componentDetails = [];
        foreach ($structure->components as $comp) {
            $compAmt = 0;
            if ($comp->is_custom) {
                $compAmt = round(($comp->amount / $totalDays) * $effectiveDays, 2);
            } else {
                $compAmt = round(($comp->percentage / 100) * $base, 2);
            }
            $componentDetails[] = [
                'name' => $comp->componentType->name ?? ($comp->name ?? 'Component'),
                'category' => $comp->componentType->category ?? 'earning',
                'amount' => $compAmt,
            ];
        }

        // prepare payslip filename (used both in DB and file save)
        $payslipFileName = 'payslips/' . $employee->id . '_' . $month . '.json';

        // ✅ Prepare Payroll Data Array
        $data = [
            'organization_id'     => $organizationId,
            'employee_id'         => $employee->id,
            'salary_structure_id' => $structure->id,
            'pay_period'          => $month,
            'from_date'           => $fromDate,
            'to_date'             => $toDate,
            'gross_earnings'      => round($earnings, 2),
            'gross_deductions'    => round($deductions + $tax, 2),
            'net_salary'          => round($net, 2),
            'working_days'        => $workingDays,
            'present_days'        => $presents,
            'half_days'           => $halfDays,
            // store total leave days (sum of paid + unpaid days within the payroll month)
            'leave_days'          => ($paidLeaveDays + $unpaidDays),
            'payslip_link'        => $payslipFileName,
            'overtime_hours'      => $overtimeHours,
            'overtime_amount'     => round($overtimePay, 2),
            'tax_deducted'        => round($tax, 2),
            'pf_contribution'     => round($pfAmount ?? 0, 2),
            'esi_contribution'    => round($esiAmount ?? 0, 2),
            'payment_status'      => 'pending',
            'payment_date'        => null,
            'payment_method'      => null,
            'transaction_ref'     => null,
            'component_breakdown' => json_encode($componentDetails ?? []),
            'remarks'             => $remarks ?? null,
        ];

        $payroll = Payroll::create($data);

        // ✅ Build a detailed payslip and save as JSON in storage/app/payslips
        $payslip = [
            'organization_id' => $organizationId,
            'employee_id' => $employee->id,
            'employee_name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
            'pay_period' => $month,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'gross_earnings' => round($earnings, 2),
            'gross_deductions' => round($deductions + $tax, 2),
            'net_salary' => round($net, 2),
            'working_days' => $workingDays,
            'present_days' => $presents,
            'half_days' => $halfDays,
            'leave_days_total' => ($paidLeaveDays + $unpaidDays),
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidDays,
            'unpaid_leave_deduction' => round($unpaidLeaveDeduction, 2),
            'overtime_hours' => $overtimeHours,
            'overtime_amount' => round($overtimePay, 2),
            'tax_deducted' => round($tax, 2),
            'component_breakdown' => $componentDetails ?? [],
            'deduction_details' => [
                'unpaid_leave' => round($unpaidLeaveDeduction, 2),
                'tax' => round($tax, 2),
                // other deductions (pf/esi) could be added here
            ],
        ];

        try {
            $filePath = 'payslips/' . $employee->id . '_' . $month . '.json';
            $fullPath = public_path($filePath);
            $payroll->payslip_link = $filePath;
            $payroll->save();

            // ensure directory exists
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($fullPath, json_encode($payslip, JSON_PRETTY_PRINT));
            $this->info("🧾 Payslip saved to public/{$filePath}");

            // Attempt PDF generation if Dompdf is available
            $pdfFilePath = public_path('payslips/' . $employee->id . '_' . $month . '.pdf');
            // dd($pdfFilePath);  
            $payroll->payslip_pdf_link = $pdfFilePath;
            $payroll->save();
            // build a simple HTML representation
            $html = '<html><head><meta charset="utf-8"><title>Payslip</title><style>body{font-family: Arial, sans-serif;} table{width:100%;border-collapse:collapse;} th,td{padding:6px;border:1px solid #ddd;text-align:left;} .right{text-align:right;}</style></head><body>';
            $html .= '<h3>Payslip - ' . htmlspecialchars($employee->first_name . ' ' . ($employee->last_name ?? '')) . '</h3>';
            $html .= '<p>Period: ' . $month . ' ('
                . Carbon::parse($fromDate)->format('d-m-y')
                . ' - '
                . Carbon::parse($toDate)->format('d-m-y')
                . ')</p>';

            $html .= '<h4>Earnings</h4><table><tbody>';
            $html .= '<tr><td>' . htmlspecialchars('Basic Pay') . '</td><td class="right">' . round(($base / $totalDays) * $effectiveDays, 2) . '</td></tr>';
            foreach ($componentDetails as $c) {
                $html .= '<tr><td>' . htmlspecialchars($c['name']) . '</td><td class="right">' . number_format($c['amount'], 2) . '</td></tr>';
            }
            $html .= '<tr><td>Overtime</td><td class="right">' . number_format($overtimePay, 2) . '</td></tr>';
            $html .= '<tr><td><strong>Gross</strong></td><td class="right"><strong>' . number_format($earnings, 2) . '</strong></td></tr>';
            $html .= '</tbody></table>';
            $html .= '<h4>Deductions</h4><table><tbody>';
            $html .= '<tr><td>Tax</td><td class="right">' . number_format($tax, 2) . '</td></tr>';
            $html .= '<tr><td>Unpaid Leave</td><td class="right">' . number_format($unpaidLeaveDeduction, 2) . '</td></tr>';
            $html .= '<tr><td><strong>Total Deductions</strong></td><td class="right"><strong>' . number_format(($deductions + $tax), 2) . '</strong></td></tr>';
            $html .= '</tbody></table>';
            $html .= '<h4>Net Pay: ' . number_format($net, 2) . '</h4>';
            $html .= '</body></html>';

            if (class_exists('\\Dompdf\\Dompdf')) {
                try {
                    $dompdfClass = '\\Dompdf\\Dompdf';
                    $dompdf = new $dompdfClass();
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4');
                    $dompdf->render();
                    file_put_contents($pdfFilePath, $dompdf->output());
                    $this->info('📄 PDF payslip saved to public/payslips/' . $employee->id . '_' . $month . '.pdf');
                } catch (Exception $e) {
                    $this->error('Failed to generate PDF payslip: ' . $e->getMessage());
                }
            } else {
                // save HTML fallback for now
                file_put_contents(public_path('payslips/' . $employee->id . '_' . $month . '.html'), $html);
                $this->warn('Dompdf not installed. HTML payslip saved as fallback. To enable PDF generation run: composer require dompdf/dompdf');
            }
        } catch (Exception $e) {
            $this->error('Failed to save payslip: ' . $e->getMessage());
        }
    }


    /**
     * Tax Calculation
     */
    // private function calculateTax($annualIncome, $orgId, $regime = 'new')
    // {
    //     $slabs = TaxSlab::where('organization_id', $orgId)
    //         ->where('tax_regime', $regime)
    //         ->orderBy('min_income')
    //         ->get();

    //     $tax = 0;
    //     foreach ($slabs as $slab) {
    //         if ($annualIncome > $slab->min_income) {
    //             $upper = $slab->max_income ?? $annualIncome;
    //             $amount = min($annualIncome, $upper) - $slab->min_income;
    //             $tax += ($amount * $slab->tax_rate) / 100;
    //         }
    //     }
    //     return $tax;
    // }


    private function calculateTax($annualIncome, $orgId, $regime = 'new')
{
    $slabs = TaxSlab::where('organization_id', $orgId)
        ->where('tax_regime', $regime)
        ->orderBy('min_income')
        ->get();

    $tax = 0;

    foreach ($slabs as $slab) {
        if ($annualIncome > $slab->min_income) {
            
            $upperLimit = $slab->max_income ?? $annualIncome;

            // Calculate slab taxable amount
            $taxableAmount = min($annualIncome, $upperLimit) - $slab->min_income;

            // Step 1: Base tax for this slab
            $slabTax = ($taxableAmount * $slab->tax_rate) / 100;

            // Step 2: Surcharge (percentage of tax)
            $surchargeAmount = 0;
            if (!empty($slab->surcharge)) {
                $surchargeAmount = ($slabTax * $slab->surcharge) / 100;
            }

            // Step 3: Cess (percentage of (tax + surcharge))
            $cessAmount = 0;
            if (!empty($slab->cess)) {
                $cessAmount = (($slabTax + $surchargeAmount) * $slab->cess) / 100;
            }

            // Add all to total tax
            $tax += ($slabTax + $surchargeAmount + $cessAmount);
        }
    }

    return round($tax, 2);
}




    protected function schedule(Schedule $schedule): void
    {
        // Run payroll on the 5th of every month at 03:00 AM
        $schedule->command('payroll:generate-organization')
            ->monthlyOn(5, '03:00')
            ->appendOutputTo(storage_path('logs/payroll_auto.log'));
    }
}
