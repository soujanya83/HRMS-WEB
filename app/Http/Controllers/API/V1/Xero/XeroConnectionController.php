<?php

namespace App\Http\Controllers\API\V1\Xero;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\XeroConnection;


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
}
