<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payrolls as Payroll;
use App\Models\Employee\Employee;
use App\Models\SalaryStructure;
use App\Models\TaxSlabs as TaxSlab;
use App\Models\Employee\Attendance;
use App\Models\OrganizationLeave;
use App\Models\Employee\Leave as EmployeeLeave;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Exception;
use Dompdf\Dompdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use App\Models\Organization;
use App\Models\Employee\OvertimeRequest;
use App\Models\SalaryStructureComponent;
use App\Models\SalaryComponentType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PDF;
use Dompdf\Options;
use Dompdf\Adapter\CPDF;
use Dompdf\Adapter\PDFLib;
use Dompdf\Adapter\GD;
use Dompdf\Adapter\CPDFLib;

class PayrollsController extends Controller
{
    public function index()
    {
        try {
            $employee = Employee::where('user_id', Auth::id())->firstOrFail();
            $payrolls = Payroll::where('organization_id', $employee->organization_id)
                ->with(['employee:id,first_name,last_name', 'salaryStructure'])
                ->orderBy('pay_period', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Payroll records fetched successfully.',
                'data' => $payrolls
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve payrolls.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ➕ Create payroll for an employee (Generate salary)
     */
    public function store(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'month' => 'required|date_format:m-Y',
            'employee_id' => 'required|exists:employees,id',
        ]);

        // Convert m-Y to usable Carbon object
        $carbon = Carbon::createFromFormat('m-Y', $validated['month']);

        $YYYYMM = $carbon->format('Y-m');    // ✔ "2025-10"
        $fromDate = $carbon->startOfMonth()->toDateString();  // ✔ 2025-10-01
        $toDate   = $carbon->endOfMonth()->toDateString();    // ✔ 2025-10-31

        // Fetch employee + organization
        $employee = Employee::with('salaryStructure')->findOrFail($validated['employee_id']);
        $org = Organization::findOrFail($employee->organization_id);

        // 🔍 Check uniqueness
        $exists = Payroll::where('employee_id', $employee->id)
            ->where('pay_period', $YYYYMM)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => "Payroll already exists for {$validated['month']}."
            ], 409);
        }

        try {
            DB::beginTransaction();

            // Generate payroll
            $this->generatePayrollForEmployee(
                $employee,
                $org->id,
                $YYYYMM,   // Pass the normalized month
                $toDate,
                $fromDate
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payroll generated successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error generating payroll',
                'error'   => $e->getMessage(),
            ], 500);
        }
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


            // Attempt PDF generation if Dompdf is available
            $pdfFilePath = public_path('payslips/' . $employee->id . '_' . $month . '.pdf');
            // dd($pdfFilePath);  
            $payroll->payslip_pdf_link = $pdfFilePath;
            $payroll->save();
            // build a simple HTML representation
            $html = '<html><head><meta charset="utf-8"><title>Payslip</title><style>body{font-family: Arial, sans-serif;} table{width:100%;border-collapse:collapse;} th,td{padding:6px;border:1px solid #ddd;text-align:left;} .right{text-align:right;}</style></head><body>';
            $html .= '<h3>Payslip - ' . htmlspecialchars($employee->first_name . ' ' . ($employee->last_name ?? '')) . '</h3>';
            $html .= '<p>Period: ' . $month . ' (' . $fromDate . ' - ' . $toDate . ')</p>';
            $html .= '<h4>Earnings</h4><table><tbody>';
            $html .= '<tr><td>' . htmlspecialchars('Basic Pay') . '</td><td class="right">' . number_format($earnings, 2) . '</td></tr>';
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
                } catch (Exception $e) {
                    Log::error("Dompdf PDF generation failed for employee ID {$employee->id} for month {$month}: " . $e->getMessage());
                    // save HTML fallback
                    file_put_contents(public_path('payslips/' . $employee->id . '_' . $month . '.html'), $html);
                }
            } else {
                // save HTML fallback for now
                file_put_contents(public_path('payslips/' . $employee->id . '_' . $month . '.html'), $html);
            }
        } catch (Exception $e) {
            Log::error("Failed to save payslip for employee ID {$employee->id} for month {$month}: " . $e->getMessage());
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


    /**
     * 🧮 Tax Calculation Based on Slabs
     */


    /**
     * 🧾 Show single payroll record
     */
    public function show($id)
    {
        try {
            $payroll = Payroll::with(['employee', 'salaryStructure'])->findOrFail($id);

            return response()->json([
                'status' => true,
                'message' => 'Payroll details retrieved successfully.',
                'data' => $payroll
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching payroll.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payroll (status, notes or manual adjustments)
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'sometimes|string|in:generated,locked,paid,cancelled',
            'notes' => 'sometimes|string|max:1000',
            'total_earnings' => 'sometimes|numeric|min:0',
            'total_deductions' => 'sometimes|numeric|min:0',
            'net_pay' => 'sometimes|numeric',
        ]);

        try {
            $payroll = Payroll::findOrFail($id);
            $payroll->fill($validated);
            $payroll->save();

            return response()->json([
                'status' => true,
                'message' => 'Payroll updated successfully.',
                'data' => $payroll
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error updating payroll.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ❌ Delete payroll
     */
    public function destroy($id)
    {
        try {
            $payroll = Payroll::findOrFail($id);
            $payroll->delete();

            return response()->json([
                'status' => true,
                'message' => 'Payroll deleted successfully.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error deleting payroll.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
