<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HolidayModel;
use App\Models\Employee\Employee;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class HolidayController extends Controller
{
        /**
     * Store selected state code in session.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function setState(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'state_code' => 'required|string',
        ]);

        // Store in session
        session(['state_code' => $validated['state_code']]);

        return response()->json([
            'status' => true,
            'message' => 'State code set successfully.',
            'state_code' => $validated['state_code'],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        try {

            // ✅ Validate organization_id from query
            $validated = $request->validate([
                'organization_id' => ['required', 'exists:organizations,id']
            ]);

            // ✅ Fetch holidays based on organization_id
            $holidays = HolidayModel::where('organization_id', $validated['organization_id'])
                ->orderBy('holiday_date', 'asc')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $holidays
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch holidays.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


  
    public function getAustralianStates(): JsonResponse
    {
        // Static list of Australian states/territories
        $states = [
            ["code" => "AU-ACT", "name" => "Australian Capital Territory"],
            ["code" => "AU-NSW", "name" => "New South Wales"],
            ["code" => "AU-NT", "name" => "Northern Territory"],
            ["code" => "AU-QLD", "name" => "Queensland"],
            ["code" => "AU-SA", "name" => "South Australia"],
            ["code" => "AU-TAS", "name" => "Tasmania"],
            ["code" => "AU-VIC", "name" => "Victoria"],
            ["code" => "AU-WA", "name" => "Western Australia"],
        ];
        return response()->json([
            'status' => true,
            'data' => $states,
        ]);
    }
    
    /**
     * Store a newly created holiday.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $userId = $user->id;
            $validated = $request->validate([
                'organization_id' => ['required', 'exists:organizations,id'],
                'holiday_name' => 'required|string|max:255',
                'holiday_date' => 'required|date',
                'holiday_type' => 'required|in:National,Regional,Company',
                'is_recurring' => 'boolean',
                'is_active' => 'boolean',
                'description' => 'nullable|string',
            ]);


            $state = $request->header('X-State-Code') ?? session('state_code');
            if (!$state) {
                return response()->json(['status' => false, 'message' => 'State code not set in session or header.'], 400);
            }

            $validated['created_by'] = $userId;
            $validated['state_code'] = $state;

            $holiday = HolidayModel::create($validated);

            return response()->json(['status' => true, 'message' => 'Holiday created successfully.', 'data' => $holiday]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to create holiday.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getHolidays(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => 'required|integer|exists:organizations,id',
            'center_id' => 'nullable|integer',
            'year' => 'nullable|integer',
            'month' => 'nullable', 
        ]);

        // Step 1: Get state from header or session
        $state = $request->header('X-State-Code') ?? session('state_code');
        if (!$state) {
            return response()->json([
                'status' => false,
                'message' => 'State code not set in session or header.'
            ], 400);
        }

        $year = $validated['year'] ?? now()->year;
        $month = null;
        if (!empty($validated['month'])) {
            $monthInput = $validated['month'];
            if (is_numeric($monthInput)) {
                $month = (int)$monthInput;
            } else {
                // Accept both full and short month names, case-insensitive
                $months = [
                    1 => ['january', 'jan'],
                    2 => ['february', 'feb'],
                    3 => ['march', 'mar'],
                    4 => ['april', 'apr'],
                    5 => ['may'],
                    6 => ['june', 'jun'],
                    7 => ['july', 'jul'],
                    8 => ['august', 'aug'],
                    9 => ['september', 'sep'],
                    10 => ['october', 'oct'],
                    11 => ['november', 'nov'],
                    12 => ['december', 'dec'],
                ];
                $monthStr = strtolower(trim($monthInput));
                foreach ($months as $num => $names) {
                    if (in_array($monthStr, $names, true)) {
                        $month = $num;
                        break;
                    }
                }
            }
            // If invalid, $month remains null (no filter)
        }
        $apiHolidays = collect();
        $apiFailed = false;

        // Step 2: Fetch holidays from external API
        try {
            $response = Http::timeout(5)->get("https://date.nager.at/api/v3/PublicHolidays/{$year}/AU");
            if ($response->successful()) {
                $apiData = $response->json();
                $apiHolidays = collect($apiData)
                    ->filter(function ($item) use ($state, $month) {
                        $isForState = empty($item['counties']) || (is_array($item['counties']) && in_array($state, $item['counties']));
                        $isForMonth = !$month || (Carbon::parse($item['date'])->month == $month);
                        return $isForState && $isForMonth;
                    })
                    ->map(function ($item) {
                        return [
                            'date' => Carbon::parse($item['date'])->format('Y-m-d'),
                            'name' => $item['localName'],
                            'source' => 'api',
                        ];
                    });
            } else {
                $apiFailed = true;
            }
        } catch (\Exception $e) {
            Log::error('External holiday API failed: ' . $e->getMessage());
            $apiFailed = true;
        }

        // Step 3: Fetch DB holidays
        $dbHolidays = HolidayModel::query()
            ->where('organization_id', $validated['organization_id'])
            ->where(function ($q) use ($validated, $state) {
                if (!empty($validated['center_id'])) {
                    $q->where('center_id', $validated['center_id'])
                      ->orWhere('state_code', $state);
                } else {
                    $q->where('state_code', $state);
                }
            })
            ->when($year, function ($q) use ($year) {
                $q->whereYear('holiday_date', $year);
            })
            ->when($month, function ($q) use ($month) {
                $q->whereMonth('holiday_date', $month);
            })
            ->get()
            ->map(function ($item) {
                return [
                    'date' => Carbon::parse($item->holiday_date)->format('Y-m-d'),
                    'name' => $item->holiday_name,
                    'source' => 'db',
                ];
            });

        // Step 4: Merge and deduplicate
        $allHolidays = $apiFailed
            ? $dbHolidays
            : $apiHolidays->concat($dbHolidays);

        $uniqueHolidays = $allHolidays->unique(function ($item) {
            return $item['date'] . '|' . $item['name'];
        })->sortBy('date')->values()->all();

        $response = [
            'status' => true,
            'state' => $state,
        ];
        if ($month) {
            $response['month_name'] = \Carbon\Carbon::create()->month($month)->format('F');
        }
        $response['data'] = $uniqueHolidays;
        $response['api_failed'] = $apiFailed;
        return response()->json($response);
    }

    /**
     * Show a specific holiday.
     */
    public function show($id): JsonResponse
    {
        try {
            $holiday = HolidayModel::find($id);

            if (!$holiday) {
                return response()->json(['status' => false, 'message' => 'Holiday not found.'], 404);
            }

            return response()->json(['status' => true, 'data' => $holiday]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to fetch holiday details.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a holiday.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $holiday = HolidayModel::find($id);

            if (!$holiday) {
                return response()->json(['status' => false, 'message' => 'Holiday not found.'], 404);
            }

            $validated = $request->validate([
                'holiday_name' => 'nullable|string|max:255',
                'holiday_date' => 'nullable|date',
                'holiday_type' => 'nullable|in:National,Regional,Company',
                'is_recurring' => 'boolean',
                'is_active' => 'boolean',
                'description' => 'nullable|string',
            ]);

            $holiday->update($validated);

            return response()->json(['status' => true, 'message' => 'Holiday updated successfully.', 'data' => $holiday]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update holiday.', 'error' => $e->getMessage()], 500);
        }
    }

    public function partialUpdate(Request $request, $id): JsonResponse
    {
        try {
            // 🔍 Find holiday record
            $holiday = HolidayModel::find($id);

            if (!$holiday) {
                return response()->json([
                    'status' => false,
                    'message' => 'Holiday not found.'
                ], 404);
            }

            // ✅ Validate only the fields that might be passed
            $validated = $request->validate([
                'is_recurring' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
            ]);

            // Default message
            $message = 'Holiday updated successfully.';

            // 🟢 Handle is_active toggle
            if ($request->has('is_active')) {
                $holiday->is_active = $validated['is_active'];
                $message = $validated['is_active']
                    ? 'Holiday activated successfully.'
                    : 'Holiday deactivated successfully.';
            }

            // 🔁 Handle is_recurring toggle
            if ($request->has('is_recurring')) {
                $holiday->is_recurring = $validated['is_recurring'];
                $message = $validated['is_recurring']
                    ? 'Holiday recurring enabled successfully.'
                    : 'Holiday recurring disabled successfully.';
            }

            // 💾 Save only if any changes are made
            if ($holiday->isDirty()) {
                $holiday->save();
            }

            // ✅ Response
            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $holiday
            ]);
            } catch (\Exception $e) {
                // ❌ Exception handler
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to update holiday.',
                    'error' => $e->getMessage()
                ], 500);
            }
    }


    /**
     * Delete a holiday.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $holiday = HolidayModel::find($id);

            if (!$holiday) {
                return response()->json(['status' => false, 'message' => 'Holiday not found.'], 404);
            }

            $holiday->delete();

            return response()->json(['status' => true, 'message' => 'Holiday deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to delete holiday.', 'error' => $e->getMessage()], 500);
        }
    }


    public function upcomingHolidays(Request $request): JsonResponse
    {
        try {

            /* ============================
            | 1. VALIDATION
            ============================ */
            $validated = $request->validate([
                'organization_id' => ['required', 'exists:organizations,id']
            ]);

            $today = now()->toDateString();

            /* ============================
            | 2. FETCH UPCOMING HOLIDAYS
            ============================ */
            $holidays = HolidayModel::where('organization_id', $validated['organization_id'])
                ->whereDate('holiday_date', '>=', $today) // only future + today
                ->orderBy('holiday_date', 'asc')
                ->limit(5)
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Upcoming holidays fetched successfully',
                'data' => $holidays
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch upcoming holidays',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
