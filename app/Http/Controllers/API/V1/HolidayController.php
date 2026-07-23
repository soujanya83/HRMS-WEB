<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HolidayModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class HolidayController extends Controller
{
    /**
     * SYNC API: Fetch and store Australian public holidays from Nager.Date API
     */
    public function syncAustralianHolidays(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year', now()->year);
            
            // Fetch holidays from external source
            $response = Http::timeout(10)->get("https://date.nager.at/api/v3/PublicHolidays/{$year}/AU");
            
            if (!$response->successful()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to reach external holiday API service.'
                ], 502);
            }

            $externalHolidays = $response->json();
            $recordsSynced = 0;

            foreach ($externalHolidays as $holiday) {
                $isGlobal = $holiday['global'] ?? empty($holiday['counties']);
                $holidayDate = Carbon::parse($holiday['date'])->format('Y-m-d');
                $holidayName = $holiday['localName'] ?? $holiday['name'];

                if ($isGlobal) {
                    // National Holiday: Applies across all states (state_code is null)
                    HolidayModel::updateOrCreate(
                        [
                            'holiday_date' => $holidayDate,
                            'holiday_name' => $holidayName,
                            'organization_id' => null, 
                            'state_code' => null, 
                        ],
                        [
                            'holiday_type' => 'National',
                            'is_recurring' => $holiday['fixed'] ?? false,
                            'description' => 'Official Australian National Public Holiday',
                            'is_active' => true,
                            'created_by' => Auth::id(),
                        ]
                    );
                    $recordsSynced++;
                } else {
                    // Regional Holiday: Split and process per defined state context
                    foreach ($holiday['counties'] as $stateCode) {
                        HolidayModel::updateOrCreate(
                            [
                                'holiday_date' => $holidayDate,
                                'holiday_name' => $holidayName,
                                'organization_id' => null,
                                'state_code' => $stateCode, // e.g., "AU-NSW"
                            ],
                            [
                                'holiday_type' => 'Regional',
                                'is_recurring' => $holiday['fixed'] ?? false,
                                'description' => "Regional Public Holiday for State {$stateCode}",
                                'is_active' => true,
                                'created_by' => Auth::id(),
                            ]
                        );
                        $recordsSynced++;
                    }
                }
            }

            return response()->json([
                'status' => true,
                'message' => "Successfully synced {$recordsSynced} holiday entries for the year {$year}."
            ], 200);

        } catch (\Exception $e) {
            Log::error('Holiday synchronization failed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Internal server processing crash occurred while updating context.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Core Fetch Engine: Combines Organization Custom + Global Public Holidays
     */
    public function getHolidays(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => 'required|integer|exists:organizations,id',
            'center_id' => 'nullable|integer',
            'year' => 'nullable|integer',
            'month' => 'nullable', 
        ]);

        $state = $request->header('X-State-Code') ?? session('state_code');
        if (!$state) {
            return response()->json([
                'status' => false,
                'message' => 'State code contextual baseline configuration missing from headers or session.'
            ], 400);
        }

        $year = $validated['year'] ?? now()->year;
        $month = $this->parseMonthVariant($validated['month'] ?? null);

        // Fetch mixed calendar entries contextually match configuration
        $holidays = HolidayModel::query()
            ->where(function ($query) use ($validated, $state) {
                // 1. Fetch custom company structural updates
                $query->where('organization_id', $validated['organization_id']);
                
                // 2. Fetch global synced public updates context
                $query->orWhere(function ($q) use ($state) {
                    $q->whereNull('organization_id')
                      ->where(function ($sub) use ($state) {
                          $sub->whereNull('state_code') // National
                              ->orWhere('state_code', $state); // State specific matching
                      });
                });
            })
            ->when($year, function ($q) use ($year) {
                $q->whereYear('holiday_date', $year);
            })
            ->when($month, function ($q) use ($month) {
                $q->whereMonth('holiday_date', $month);
            })
            ->orderBy('holiday_date', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'date' => Carbon::parse($item->holiday_date)->format('Y-m-d'),
                    'name' => $item->holiday_name,
                    'type' => $item->holiday_type,
                    'state_code' => $item->state_code,
                    'is_recurring' => $item->is_recurring,
                    'is_active' => $item->is_active,
                    'source' => is_null($item->organization_id) ? 'public_holiday' : 'custom_organization',
                ];
            });

        $response = [
            'status' => true,
            'state_code' => $state,
            'year' => $year,
            'data' => $holidays
        ];

        if ($month) {
            $response['month_name'] = Carbon::create()->month($month)->format('F');
        }

        return response()->json($response);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'organization_id' => ['required', 'exists:organizations,id']
            ]);

            $state = $request->header('X-State-Code') ?? session('state_code');

            // Fallback strategy if state context isn't passed to return everything or filter strictly
            $holidays = HolidayModel::where('organization_id', $validated['organization_id'])
                ->when($state, function ($q) use ($state) {
                    $q->orWhere(function($sub) use ($state) {
                        $sub->whereNull('organization_id')
                            ->where(function($inner) use ($state) {
                                $inner->whereNull('state_code')->orWhere('state_code', $state);
                            });
                    });
                })
                ->orderBy('holiday_date', 'asc')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $holidays
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch database context collections.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function upcomingHolidays(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'organization_id' => ['required', 'exists:organizations,id']
            ]);

            $state = $request->header('X-State-Code') ?? session('state_code');
            if (!$state) {
                return response()->json(['status' => false, 'message' => 'X-State-Code header is missing.'], 400);
            }

            $today = now()->toDateString();

           $holidays = HolidayModel::where(function ($query) use ($validated, $state) {
            $query->where(function ($q) use ($validated, $state) {
                $q->where('organization_id', $validated['organization_id'])
                ->where('state_code', $state);
            })
            ->orWhere(function ($q) use ($state) {
                $q->whereNull('organization_id')
                ->where('state_code', $state);
            });
        })
        ->whereDate('holiday_date', '>=', $today)
        ->orderBy('holiday_date', 'asc')
        ->limit(5)
        ->get();

            return response()->json([
                'status' => true,
                'message' => 'Upcoming timeline updates synchronized cleanly.',
                'data' => $holidays
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve linear timeline updates.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
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
                return response()->json(['status' => false, 'message' => 'State baseline identifier tracking missing.'], 400);
            }

            $validated['created_by'] = Auth::id();
            $validated['state_code'] = $state;

            $holiday = HolidayModel::create($validated);
            return response()->json(['status' => true, 'message' => 'Holiday customized entry logged successfully.', 'data' => $holiday]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to write record structure configuration changes.', 'error' => $e->getMessage()], 500);
        }
    }

    public function setState(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'state_code' => 'required|string',
        ]);

        session(['state_code' => $validated['state_code']]);

        return response()->json([
            'status' => true,
            'message' => 'State code tracking successfully updated.',
            'state_code' => $validated['state_code'],
        ]);
    }

    public function getAustralianStates(): JsonResponse
    {
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
        return response()->json(['status' => true, 'data' => $states]);
    }

    public function show($id): JsonResponse
    {
        try {
            $holiday = HolidayModel::find($id);
            if (!$holiday) return response()->json(['status' => false, 'message' => 'Entry not located.'], 404);
            return response()->json(['status' => true, 'data' => $holiday]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $holiday = HolidayModel::find($id);
            if (!$holiday) return response()->json(['status' => false, 'message' => 'Target not found.'], 404);

            $validated = $request->validate([
                'holiday_name' => 'nullable|string|max:255',
                'holiday_date' => 'nullable|date',
                'holiday_type' => 'nullable|in:National,Regional,Company',
                'is_recurring' => 'boolean',
                'is_active' => 'boolean',
                'description' => 'nullable|string',
            ]);

            $holiday->update($validated);
            return response()->json(['status' => true, 'message' => 'Updated successfully.', 'data' => $holiday]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function partialUpdate(Request $request, $id): JsonResponse
    {
        try {
            $holiday = HolidayModel::find($id);
            if (!$holiday) return response()->json(['status' => false, 'message' => 'Record variant identity validation error.'], 404);

            $validated = $request->validate([
                'is_recurring' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
            ]);

            $message = 'Holiday parameters localized clean update complete.';

            if ($request->has('is_active')) {
                $holiday->is_active = $validated['is_active'];
                $message = $validated['is_active'] ? 'Holiday record row set active.' : 'Holiday entry deactivated.';
            }

            if ($request->has('is_recurring')) {
                $holiday->is_recurring = $validated['is_recurring'];
                $message = $validated['is_recurring'] ? 'Recurrence parameter tracking locked.' : 'Recurrence profile cleared.';
            }

            if ($holiday->isDirty()) {
                $holiday->save();
            }

            return response()->json(['status' => true, 'message' => $message, 'data' => $holiday]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $holiday = HolidayModel::find($id);
            if (!$holiday) return response()->json(['status' => false, 'message' => 'Object does not exist.'], 404);
            $holiday->delete();
            return response()->json(['status' => true, 'message' => 'Entry purged successfully.']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Parse Month helper engine
     */
    private function parseMonthVariant($monthInput)
    {
        if (empty($monthInput)) return null;
        if (is_numeric($monthInput)) return (int)$monthInput;

        $months = [
            1 => ['january', 'jan'], 2 => ['february', 'feb'], 3 => ['march', 'mar'],
            4 => ['april', 'apr'], 5 => ['may'], 6 => ['june', 'jun'],
            7 => ['july', 'jul'], 8 => ['august', 'aug'], 9 => ['september', 'sep'],
            10 => ['october', 'oct'], 11 => ['november', 'nov'], 12 => ['december', 'dec'],
        ];
        
        $monthStr = strtolower(trim($monthInput));
        foreach ($months as $num => $names) {
            if (in_array($monthStr, $names, true)) {
                return $num;
            }
        }
        return null;
    }
}