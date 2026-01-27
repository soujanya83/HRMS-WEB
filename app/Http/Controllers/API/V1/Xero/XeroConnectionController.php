<?php

namespace App\Http\Controllers\API\V1\Xero;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\XeroConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;



class XeroConnectionController extends Controller
{
    public function index()
    {
        try {

            $connections = XeroConnection::orderBy('id', 'desc')
                ->paginate(20);

            // If ZERO xero_connections exist
            if ($connections->total() === 0) {
                return response()->json([
                    'status' => true,
                    'message' => 'No Xero connections found.',
                    'data' => [],
                ]);
            }

            // If connections exist but SOME have no employeeConnections
            $connections->getCollection()->transform(function ($item) {
                $item->has_employee_connections = $item->employeeConnections->isNotEmpty();
                return $item;
            });

            return response()->json([
                'status' => true,
                'data' => $connections
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while fetching connections.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Store a newly created Xero connection.
     */
    public function store(Request $request)
    {
        try {

            $request->merge([
                'organization_id' => (int) $request->organization_id,
            ]);

            $validated = $request->validate([
                'organization_id' => 'required|numeric|exists:organizations,id',
                'tenant_id' => 'required|string|max:255|unique:xero_connections,tenant_id',
                'tenant_name' => 'required|string|max:255',
                'tenant_type' => 'required|string|max:255',
                'access_token' => 'required|string',
                'refresh_token' => 'required|string',
                'id_token' => 'nullable|string',
                'token_expires_at' => 'required|date_format:Y-m-d H:i:s',
                'refresh_token_expires_at' => 'nullable|date_format:Y-m-d H:i:s',
                'country_code' => 'nullable|string|max:2',
                'organisation_type' => 'nullable|string|max:255',
                'scopes' => 'nullable',
                'xero_organization_name' => 'required|string',
                'xero_client_id' => 'required|string',
                'xero_client_secret' => 'required|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {

            // This will show EXACTLY which field failed and the message
            return response()->json([
                'status' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        // Convert scopes array → json
        if (is_array($request->scopes)) {
            $validated['scopes'] = json_encode($request->scopes);
        }

        $validated['connected_at'] = now();
        $validated['is_active'] = true;

        $connection = XeroConnection::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Xero connection created successfully.',
            'data' => $connection
        ]);
    }


    /**
     * Display the specified connection.
     */
    public function show($id)
    {
        $connection = XeroConnection::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $connection
        ]);
    }

    /**
     * Update the specified connection.
     */
    public function update(Request $request, $id)
    {
        try {

            // Validate request input
            $validated = $request->validate([
                'tenant_name' => 'nullable|string|max:255',
                'tenant_type' => 'nullable|string|max:255',
                'access_token' => 'nullable|string',
                'refresh_token' => 'nullable|string',
                'id_token' => 'nullable|string',
                'token_expires_at' => 'nullable|date_format:Y-m-d H:i:s',
                'refresh_token_expires_at' => 'nullable|date_format:Y-m-d H:i:s',
                'country_code' => 'nullable|string|max:2',
                'organisation_type' => 'nullable|string|max:255',
                'scopes' => 'nullable|array',
                'is_active' => 'nullable|boolean',
            ]);

            // Debug incoming request
            // dd($request->all());

            // Find connection safely
            $connection = XeroConnection::findOrFail($id);

            // Convert scopes array → JSON
            if (isset($validated['scopes']) && is_array($validated['scopes'])) {
                $validated['scopes'] = json_encode($validated['scopes']);
            }

            // Assign fields manually
            foreach ($validated as $key => $value) {
                $connection->{$key} = $value;
            }

            // Handle deactivation timestamp
            if (array_key_exists('is_active', $validated)) {
                if ($validated['is_active'] == false) {
                    $connection->disconnected_at = now();
                } else {
                    $connection->disconnected_at = null;
                }
            }

            // Save the record
            $connection->save();

            return response()->json([
                'status' => true,
                'message' => 'Connection saved successfully (using save()).',
                'data' => $connection->fresh()
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'status' => false,
                'message' => 'Xero connection not found.',
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while saving the connection.',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * Soft delete connection.
     */
    public function destroy($id)
    {
        $connection = XeroConnection::findOrFail($id);
        $connection->delete();

        return response()->json([
            'status' => true,
            'message' => 'Connection removed successfully.'
        ]);
    }

  public function connect(Request $request)
{
    $user = $request->user(); // sanctum user

    $state = encrypt(json_encode([
        'user_id' => $user->id,
        'organization_id' => $user->organization_id,
    ]));

    $query = http_build_query([
        'response_type' => 'code',
        'client_id'     => config('services.xero.client_id'),
        'redirect_uri'  => config('services.xero.redirect'),
        'scope'         => config('services.xero.scopes'),
        'state'         => $state,
    ]);

    return response()->json([
        'auth_url' => 'https://login.xero.com/identity/connect/authorize?' . $query
    ]);
}



public function callback(Request $request)
{
    Log::info('Xero callback hit', $request->all());

    if (!$request->code || !$request->state) {
        abort(400, 'Invalid callback');
    }

    try {
        $state = json_decode(decrypt($request->state), true);
    } catch (\Exception $e) {
        Log::error('State decrypt failed', ['error' => $e->getMessage()]);
        abort(500, 'State decrypt failed');
    }

    $token = Http::asForm()->post(
        'https://identity.xero.com/connect/token',
        [
            'grant_type'    => 'authorization_code',
            'code'          => $request->code,
            'redirect_uri'  => config('services.xero.redirect'),
            'client_id'     => config('services.xero.client_id'),
            'client_secret' => config('services.xero.client_secret'),
        ]
    )->json();

    if (isset($token['error'])) {
        Log::error('Xero token error', $token);
        abort(500, 'Token exchange failed');
    }

    $tenants = Http::withToken($token['access_token'])
        ->get('https://api.xero.com/connections')
        ->json();

    if (empty($tenants)) {
        abort(500, 'No Xero tenant found');
    }

    $tenant = $tenants[0];

    XeroConnection::updateOrCreate(
    ['tenant_id' => $tenant['tenantId']], // ✅ UNIQUE
    [
        'organization_id'  => 15, // 👈 hardcoded as you asked
        'tenant_name'      => $tenant['tenantName'],
        'access_token'     => $token['access_token'],
        'refresh_token'    => $token['refresh_token'],
        'token_expires_at' => now()->addSeconds($token['expires_in']),
        'connected_at'     => now(),
        'is_active'        => true,
    ]
);
    return response('Xero connected successfully. You can close this tab.');
}


public function status(Request $request)
{
    $connection = XeroConnection::where(
        'organization_id',
        $request->user()->organization_id
    )->first();
    
    return response()->json([
        'connected' => (bool) $connection,
        'tenant' => $connection?->tenant_name,
        'expires_at' => $connection?->token_expires_at,
    ]);
}



}
