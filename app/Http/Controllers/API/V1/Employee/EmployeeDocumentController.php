<?php
namespace App\Http\Controllers\API\V1\Employee;
use App\Http\Controllers\Controller;
use App\Models\Employee\EmployeeDocument;
use App\Models\Employee\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use GuzzleHttp\Client;
use Aws\Textract\TextractClient;
use Illuminate\Support\Facades\Validator;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class EmployeeDocumentController extends Controller
{
    // public function index(Request $request)
    // {
    //     $request->validate([
    //         'organization_id' => 'required|integer'
    //     ]);

    //     $organizationId = $request->query('organization_id');

    //     return response()->json([
    //         'success' => true,
    //         'data' => EmployeeDocument::with('employee')
    //             ->where('organization_id', $organizationId)
    //             ->orderBy('issue_date', 'desc')
    //             ->get()
    //     ]);
    // }


    public function index(Request $request)
{
    $request->validate([
        'organization_id' => 'required|exists:organizations,id',
        'employee_id'     => 'nullable|exists:employees,id',
        'document_type'   => 'nullable|string',
        'verify'          => 'nullable|in:pending,approved,rejected',
        'expiry_filter'   => 'nullable|in:expired,expiring_soon,no_expiry,latest',
        'per_page'        => 'nullable|integer|min:1|max:100',
    ]);

    $query = EmployeeDocument::with([
        'employee:id,first_name,last_name,personal_email','verifier:id,name,email'
    ])
        ->where('organization_id', $request->organization_id);

    /*
    |--------------------------------------------------------------------------
    | Employee Filter
    |--------------------------------------------------------------------------
    */
    if ($request->filled('employee_id')) {
        $query->where('employee_id', $request->employee_id);
    }


    if ($request->filled('search')) {

    $query->whereHas('employee', function ($q) use ($request) {

        $q->where('first_name', 'like', "%{$request->search}%")
          ->orWhere('last_name', 'like', "%{$request->search}%");
    });

}

    /*
    |--------------------------------------------------------------------------
    | Document Type Filter
    |--------------------------------------------------------------------------
    */
    if ($request->filled('document_type')) {
        $query->where('document_type', $request->document_type);
    }

    /*
    |--------------------------------------------------------------------------
    | Verified Filter
    |--------------------------------------------------------------------------
    */
    if ($request->has('verify')) {
        $query->where('verify', $request->verify);
    }

    /*
    |--------------------------------------------------------------------------
    | Expiry Filters
    |--------------------------------------------------------------------------
    */
    if ($request->filled('expiry_filter')) {

        switch ($request->expiry_filter) {

            case 'expired':
                $query->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', '<', now());
                break;

            case 'expiring_soon':
                $query->whereBetween(
                    'expiry_date',
                    [now(), now()->addDays(30)]
                );
                break;

            case 'no_expiry':
                $query->whereNull('expiry_date');
                break;

            case 'latest':
                // handled in ordering section
                break;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Sorting
    |--------------------------------------------------------------------------
    | NULL expiry dates should always come last
    |--------------------------------------------------------------------------
    */

    $query->orderByRaw('expiry_date IS NULL')
        ->orderBy('expiry_date', 'asc');


     
        $allowedSorts = [
    'expiry_date',
    'issue_date',
    'document_type',
    'verify'
];

$sortBy = $request->sort_by ?? 'expiry_date';

$sortOrder = $request->sort_order ?? 'asc';

if (in_array($sortBy, $allowedSorts)) {

    $query->orderBy($sortBy, $sortOrder);

}

    /*
    |--------------------------------------------------------------------------
    | NULL verify values last
    |--------------------------------------------------------------------------
    */

    $query->orderByRaw('verify IS NULL');

    $documents = $query->paginate(
        $request->per_page ?? 15
    );

    return response()->json([
        'status' => true,
        'message' => 'Documents fetched successfully.',
        'data' => $documents
    ]);
}



    public function show($id)
    {
        $doc = EmployeeDocument::with(['employee', 'verifier'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $doc]);
    }

    private function extractExpiryWithAI(string $ocrText): ?string
    {
        $client = new Client();
        try {
            $response = $client->post('https://api.deepseek.com/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('services.deepseek.api_key'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => 'deepseek-chat',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You extract expiry dates from identity documents.'
                        ],
                        [
                            'role' => 'user',
                            'content' => <<<TEXT
                Extract the EXPIRY DATE from the document text below.

                Rules:
                - Choose expiry / valid-until date (not DOB or issue date)
                - Return ONLY in YYYY-MM-DD format
                - If not found, return NULL

                Document Text:
                """
                $ocrText
                """
                TEXT
                        ]
                    ],
                    'temperature' => 0,
                ],
                'timeout' => 30,
            ]);

            $body = json_decode($response->getBody(), true);
            $answer = trim($body['choices'][0]['message']['content'] ?? '');

            if ($answer !== 'NULL' && strtotime($answer)) {
                return date('Y-m-d', strtotime($answer));
            }
            return null;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            \Log::error('DeepSeek API RequestException: ' . $e->getMessage(), ['code' => $e->getCode()]);
            return null;
        } catch (\Exception $e) {
            \Log::error('DeepSeek API General Exception: ' . $e->getMessage(), ['code' => $e->getCode()]);
            return null;
        }
    }

    private function extractIssueDateWithAI(string $ocrText): ?string
    {
        $client = new Client();

        try {
            $response = $client->post('https://api.deepseek.com/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('services.deepseek.api_key'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => 'deepseek-chat',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You extract issue dates from identity documents.'
                        ],
                        [
                            'role' => 'user',
                            'content' => <<<TEXT
                Extract the ISSUE DATE from the document text below.

                Rules:
                - Choose issue / date of issue / issued on date
                - DO NOT pick Date of Birth (DOB)
                - DO NOT pick Expiry / Valid Until date
                - Return ONLY in YYYY-MM-DD format
                - If not found, return NULL

                Document Text:
                """
                $ocrText
                """
                TEXT
                        ]
                    ],
                    'temperature' => 0,
                ],
                'timeout' => 30,
            ]);

            $body = json_decode($response->getBody(), true);
            $answer = trim($body['choices'][0]['message']['content'] ?? '');

            if ($answer !== 'NULL' && strtotime($answer)) {
                return date('Y-m-d', strtotime($answer));
            }

            return null;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            \Log::error('DeepSeek Issue Date RequestException: ' . $e->getMessage(), [
                'code' => $e->getCode()
            ]);
            return null;

        } catch (\Exception $e) {
            \Log::error('DeepSeek Issue Date General Exception: ' . $e->getMessage(), [
                'code' => $e->getCode()
            ]);
            return null;
        }
    }

    private function verifyDocumentTypeWithAI(string $ocrText, string $documentType): array
    {
        $client = new Client();

        try {

            $response = $client->post('https://api.deepseek.com/chat/completions', [
                'headers' => [
                'Authorization' => 'Bearer ' . config('services.deepseek.api_key'),
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert HR document classifier.'
                    ],
                    [
                        'role' => 'user',
                        'content' => <<<TEXT

                    Available document types:

                    * Qualification Certificate
                    * Allergens Certificate
                    * SunSmart Certificate
                    * Sleep Safe Certificate
                    * Working With Children Check
                    * Police Check
                    * CPR Certificate
                    * Right to Work
                    * First Aid Certificate
                    * Anaphylaxis Certificate
                    * Mandatory Reporting
                    * Foundations of Child Safety
                    * Advanced Child Safety
                    * Food Safety Certificate

                    OCR Document Text:
                    {$ocrText}

                    Task:
                    Determine the SINGLE BEST matching document type.

                    Return ONLY valid JSON:

                    {
                    "document_type": "document name",
                    "confidence": 0-100
                    }

                    Rules:

                    * document_type must be one item from the list above.
                    * confidence must be an integer between 0 and 100.
                    * If unsure use confidence below 90.
                    * Only return confidence above 90 when the certificate title clearly matches one document type.
                    * Do not explain.
                    * Return JSON only.
                    TEXT,
                    ]
                    ],
                    'temperature' => 0,
                    ],
                    'timeout' => 30,
                    ]);


                $body = json_decode($response->getBody(), true);

                $responseContent = trim(
                    $body['choices'][0]['message']['content'] ?? ''
                );

                $data = json_decode($responseContent, true);

                $predictedType = $data['document_type'] ?? 'UNKNOWN';
                $confidence = (int)($data['confidence'] ?? 0);

                // \Log::info('Document Verification', [
                //     'selected_type'  => $documentType,
                //     'predicted_type' => $predictedType,
                //     'confidence'     => $confidence,
                // ]);

                return [
                    'match' => (
                        strtolower(trim($predictedType))
                        === strtolower(trim($documentType))
                        && $confidence >= 90
                    ),
                    'predicted_type' => $predictedType,
                    'confidence' => $confidence,
                ];
    

        } catch (\Exception $e) {

        
            \Log::error('DeepSeek Document Verification Exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'match' => false,
                'predicted_type' => 'UNKNOWN',
                'confidence' => 0,
            ];
        

        }
    }

    private function verifyEmployeeIdentityWithAI(string $ocrText, Employee $employee): array
    {
        $client = new Client();

        try {

            $employeeName = trim(implode(' ', array_filter([
                $employee->first_name,
                $employee->middle_name,
                $employee->last_name,
            ])));

            $employeeDob = $employee->date_of_birth
                ? date('Y-m-d', strtotime($employee->date_of_birth))
                : null;


            $response = $client->post(
                'https://api.deepseek.com/chat/completions',
                [

                    'headers' => [
                        'Authorization' => 'Bearer ' . config('services.deepseek.api_key'),
                        'Content-Type' => 'application/json',
                    ],

                    'json' => [

                        'model' => 'deepseek-chat',

                        'messages' => [

                            [
                                'role' => 'system',
                                'content' => 'You are an HR identity verification expert.'
                            ],

                            [

                                'role' => 'user',

                                'content' => <<<TEXT

                                                Identity Verification Rules



                                                    Employee Name:

                                                    {$employeeName}



                                                    Employee DOB:

                                                    {$employeeDob}



                                                    ------------------------------------------------



                                                    OCR TEXT



                                                    {$ocrText}



                                                    ------------------------------------------------



                                                    Your task is to determine whether the uploaded document belongs to this employee.



                                                    Step 1

                                                    Extract the FULL NAME from the OCR.



                                                    Step 2

                                                    Extract the DATE OF BIRTH if it exists.



                                                    Step 3

                                                    Compare with the employee details.



                                                    NAME MATCHING RULES



                                                    Treat the following as the SAME PERSON:



                                                    Mohammad

                                                    Mohd.

                                                    Md.

                                                    Mo.



                                                    Abdul

                                                    Abd.



                                                    Muhammad

                                                    Mohammad



                                                    Ignore:



                                                    • Upper/lower case

                                                    • Extra spaces

                                                    • Dots

                                                    • Commas

                                                    • Hyphens

                                                    • Minor OCR spelling mistakes

                                                    • Middle initials

                                                    • Missing middle names



                                                    Examples that MUST MATCH



                                                    Mohammad Adil Ali

                                                    Mohd Adil Ali

                                                    Md Adil Ali

                                                    Mo. Adil Ali

                                                    Mohammad A. Ali



                                                    Examples that MUST NOT MATCH

                                                    Robert Testuser

                                                    John Smith

                                                    Adil Khan

                                                    Ali Mohammad (when employee is Mohammad Ali)

                                                    The order of first name and last name is important unless it is a common cultural variation.



                                                    DOB RULES



                                                    Examples:



                                                    28/10/1975



                                                    1975-10-28



                                                    28-Oct-1975



                                                    should be treated as SAME date.

                                                    



                                                    If the document contains a DOB,

                                                    it MUST match the employee DOB.



                                                    If the document does NOT contain a DOB,

                                                    do NOT reduce the confidence.



                                                    If the document contains a DOB and it is different from the employee DOB,
                                                    the confidence MUST be below 70 even if the name matches perfectly.

                                                    If the document does not contain a DOB,
                                                    evaluate only the name.

                                                    Never invent or assume a DOB.



                                                    DECISION RULES



                                                    1. Name matches + DOB matches

                                                    -> confidence 95-100



                                                    2. Name matches + DOB missing

                                                    -> confidence 95-100



                                                    3. Name matches + DOB different

                                                    -> confidence below 70



                                                    4. Name different

                                                    -> confidence below 70



                                                    Return ONLY valid JSON.

                                                    Never return markdown.
                                                    Never return explanations.
                                                    Never wrap JSON inside ``` blocks.



                                                    {

                                                        "document_name":"",

                                                        "document_dob":null,

                                                        "confidence":98,

                                                        "reason":"..."

                                                    }

                                                TEXT

                            ]

                        ],

                        'temperature' => 0,

                    ],

                    'timeout' => 30

                ]
            );

            $body = json_decode($response->getBody(), true);

            $content = trim(
                $body['choices'][0]['message']['content'] ?? ''
            );

            $data = json_decode($content, true);

            return [

                'confidence' => (int)($data['confidence'] ?? 0),

                'document_name' => $data['document_name'] ?? null,

                'document_dob' => $data['document_dob'] ?? null,

                'reason' => $data['reason'] ?? 'No reason returned.'

            ];

        } catch (\Exception $e) {

            \Log::error('DeepSeek Employee Identity Verification', [

                'error' => $e->getMessage()

            ]);

            return [

                'confidence' => 0,

                'document_name' => null,

                'document_dob' => null,

                'reason' => 'AI verification failed.'

            ];
        }
    }


    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'employee_id'   => 'required|exists:employees,id',
    //         'document_type' => 'required|string|max:190',
    //         'file_name'     => 'required|string|max:191',
    //         'file'          => 'required|file|max:10240', // 10MB
    //         'issue_date'    => 'nullable|date',
    //         'expiry_date'   => 'nullable|date|after_or_equal:issue_date',
    //     ]);
    //     $path = $request->file('file')->store('employee_docs', 'public');
    //     $validated['file_url'] = Storage::url($path);

    //     $doc = EmployeeDocument::create($validated);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Document uploaded successfully',
    //         'data' => $doc
    //     ], 201);
    // }

    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'employee_id'   => 'required|exists:employees,id',
    //         'document_type' => 'required|string|max:190',
    //         'file_name'     => 'required|string|max:191',
    //         'file'          => 'required|file|mimes:jpg,jpeg,png|max:10240',
    //         'issue_date'    => 'nullable|date',
    //     ]);
    //     $path = $request->file('file')->store('employee_docs', 'public');
    //     $validated['file_url'] = Storage::url($path);
    //     $fileBytes = file_get_contents(storage_path('app/public/' . $path));
    //     $ocrText = '';
    //     try {
    //         $textract = new TextractClient([
    //             'region'  => env('AWS_DEFAULT_REGION'),
    //             'version' => 'latest',
    //             'credentials' => [
    //                 'key'    => env('AWS_ACCESS_KEY_ID'),
    //                 'secret' => env('AWS_SECRET_ACCESS_KEY'),
    //             ],
    //         ]);

    //         $result = $textract->analyzeDocument([
    //             'Document' => [
    //                 'Bytes' => $fileBytes,
    //             ],
    //             'FeatureTypes' => ['FORMS'],
    //         ]);

    //         foreach ($result['Blocks'] as $block) {
    //             if ($block['BlockType'] === 'LINE') {
    //                 $ocrText .= $block['Text'] . "\n";
    //             }
    //         }
    //     } catch (\Throwable $e) {
    //         \Log::error('Textract failed', ['error' => $e->getMessage()]);
    //     }

    //     if (!$ocrText) {
    //         throw ValidationException::withMessages([
    //             'file' => 'Unable to read document text.'
    //         ]);
    //     }
         
    //     $expiryDate = $this->extractExpiryWithAI($ocrText);
    //     if (!$expiryDate) {
    //         throw ValidationException::withMessages([
    //             'expiry_date' => 'Expiry date could not be detected automatically. Please enter manually.'
    //         ]);
    //     }

    //     $validated['expiry_date'] = $expiryDate;
    //     $doc = EmployeeDocument::create($validated);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Document uploaded successfully',
    //         'data'    => $doc
    //     ], 201);
    // }

    //   without  type match 
    // public function storeFlexible(Request $request)
    // {
    //     try {
    //         $validated = $request->validate([
    //             'employee_id'   => 'required|exists:employees,id',
    //             'document_type' => 'required|string|max:190',
    //             'file_name'     => 'nullable|string|max:191',
    //             'file'          => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
    //             'issue_date'    => 'nullable|date',
    //             'expiry_date'   => 'nullable|date',
    //         ]);

    //         // Upload file
    //         $path = $request->file('file')->store('employee_docs', 'public');
    //         $validated['file_url'] = Storage::url($path);

    //         $ocrText = '';

    //         // 👉 Only run OCR if needed
    //         if (!$request->issue_date || !$request->expiry_date) {

    //             try {
    //                 $fileBytes = file_get_contents(storage_path('app/public/' . $path));

    //                 $textract = new TextractClient([
    //                     'region'  => env('AWS_DEFAULT_REGION'),
    //                     'version' => 'latest',
    //                     'credentials' => [
    //                         'key'    => env('AWS_ACCESS_KEY_ID'),
    //                         'secret' => env('AWS_SECRET_ACCESS_KEY'),
    //                     ],
    //                 ]);

    //                 $result = $textract->analyzeDocument([
    //                     'Document' => ['Bytes' => $fileBytes],
    //                     'FeatureTypes' => ['FORMS'],
    //                 ]);

    //                 foreach ($result['Blocks'] as $block) {
    //                     if ($block['BlockType'] === 'LINE') {
    //                         $ocrText .= $block['Text'] . "\n";
    //                     }
    //                 }

    //             } catch (\Throwable $e) {
    //                 \Log::error('Textract failed', ['error' => $e->getMessage()]);
    //             }

    //             // 👉 Try AI extraction only if OCR found something
    //             if ($ocrText) {
    //                 if (!$request->expiry_date) {
    //                     $validated['expiry_date'] = $this->extractExpiryWithAI($ocrText);
    //                 }

    //                 if (!$request->issue_date) {
    //                     $validated['issue_date'] = $this->extractIssueDateWithAI($ocrText);
    //                 }
    //             }
    //         }

    //         // ✅ Save anyway even if no dates found
    //         $doc = EmployeeDocument::create($validated);

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Document uploaded successfully',
    //             'data'    => $doc
    //         ], 201);

    //     } catch (\Exception $e) {

    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ]);
    //     }
    // }

    public function storeFlexible(Request $request)
    {
        try {

            $validated = $request->validate([
                'organization_id' => 'required|integer',
                'employee_id'   => 'required|exists:employees,id',
                'document_type' => 'required|string|max:190',
                'file_name'     => 'nullable|string|max:191',
                'file'          => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
                'issue_date'    => 'nullable|date',
                'expiry_date'   => 'nullable|date|after:issue_date',
                'force_save'    => 'nullable|in:true,false',     //true||false
            ]);

            // Upload file
            $path = $request->file('file')->store('employee_docs', 'public');

            $validated['file_url'] = Storage::url($path);

            $ocrText = '';
            $employee = Employee::withTrashed()
                ->with('user')
                ->find($request->employee_id);

            if (!$employee) {

                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found.',
                ], 404);
            }

            if ($employee->trashed()) {

                return response()->json([
                    'success' => false,
                    'message' => 'This employee has been deleted. Document upload is not allowed.',
                ], 422);
            }
        
            $forceSave = filter_var($request->force_save, FILTER_VALIDATE_BOOLEAN);

            $verification = [
                'match' => false,
                'predicted_type' => null,
                'confidence' => 0,
            ];

            
            $nameVerification = [
                'confidence' => 0,
                'document_name' => null,
                'document_dob' => null,
                'reason' => null,
            ];
            // Run OCR only when one of the dates is missing
            if (empty($request->issue_date) || empty($request->expiry_date)) {

                try {

                    $fileBytes = file_get_contents(
                        storage_path('app/public/' . $path)
                    );

                    // $textract = new TextractClient([
                    //     'region'  => env('AWS_DEFAULT_REGION'),
                    //     'version' => 'latest',
                    //     'credentials' => [
                    //         'key'    => env('AWS_ACCESS_KEY_ID'),
                    //         'secret' => env('AWS_SECRET_ACCESS_KEY'),
                    //     ],
                    // ]);
                    $textract = new TextractClient([
                                'region'  => config('services.ses.region'),
                                'version' => 'latest',
                                'credentials' => [
                                    'key'    => config('services.ses.key'),
                                    'secret' => config('services.ses.secret'),
                                ],
                            ]);

                    $result = $textract->analyzeDocument([
                        'Document' => [
                            'Bytes' => $fileBytes,
                        ],
                        'FeatureTypes' => ['FORMS'],
                    ]);

                    foreach ($result['Blocks'] as $block) {
                        if (
                            isset($block['BlockType']) &&
                            $block['BlockType'] === 'LINE'
                        ) {
                            $ocrText .= $block['Text'] . "\n";
                        }
                    }

                                /*
                            |--------------------------------------------------------------------------
                            | Verify Employee Identity
                            |--------------------------------------------------------------------------
                            */

                            $nameVerification = $this->verifyEmployeeIdentityWithAI(
                                $ocrText,
                                $employee
                            );

                            /*
                            |--------------------------------------------------------------------------
                            | Hard Reject
                            |--------------------------------------------------------------------------
                            */

                            if ($nameVerification['confidence'] < 70) {

                                return response()->json([

                                    'success' => false,

                                    'message' =>
                                        'Identity verification failed.',

                                    'reason' =>
                                    $nameVerification['reason'],

                                    'employee_name' =>
                                        $employee->user->name,

                                    'employee_dob' =>
                                        $employee->date_of_birth,

                                    'document_name' =>
                                        $nameVerification['document_name'],

                                    'document_dob' =>
                                        $nameVerification['document_dob'],

                                    'identity_confidence' =>
                                        $nameVerification['confidence'],

                                    'force_save' => false

                                ], 422);
                            }

                } catch (\Throwable $e) {

                    \Log::error('Textract failed', [
                        'error' => $e->getMessage()
                    ]);
                }


                // check if uploded document is same as document
                if (!empty($ocrText)) {

                    $verification = $this->verifyDocumentTypeWithAI(
                    $ocrText,
                    $request->document_type
                    );
                    

                    // Default values
                    $validated['verify'] = 'pending';
                    $validated['verified_by'] = null;
                    $validated['verified_by_ai'] = 'no';

                    /*
                    |--------------------------------------------------------------------------
                    | Document Type Verification
                    |--------------------------------------------------------------------------
                    */

                    if ($verification['match'] && $verification['confidence'] >= 90) {

                        /*
                        |--------------------------------------------------------------------------
                        | Identity also matched
                        |--------------------------------------------------------------------------
                        */

                        if ($nameVerification['confidence'] >= 95) {

                            $validated['verified_by_ai'] = 'yes';

                        } else {

                            // Identity confidence is 70-94
                            $validated['verified_by_ai'] = 'no';
                        }

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Wrong Document Type
                        |--------------------------------------------------------------------------
                        */

                        if (!$forceSave) {

                            return response()->json([

                                'success' => false,

                                'message' => 'Uploaded document does not match the selected document type.',

                                'selected_type' => $request->document_type,

                                'detected_type' => $verification['predicted_type'],

                                'detected_type_confidence' => $verification['confidence'],

                                // 'employee_name' => $employee->user->name,

                                // 'employee_dob' => $employee->date_of_birth,

                                // 'document_name' => $nameVerification['document_name'],

                                // 'document_dob' => $nameVerification['document_dob'],

                                // 'identity_confidence' => $nameVerification['confidence'],

                                'force_save' => false

                            ], 422);
                        }

                        // force_save=true
                        // Save with verified_by_ai = no
                    }
 

                }


                // Extract dates only if OCR text exists
                if (!empty($ocrText)) {

                    if (empty($request->expiry_date)) {
                        $validated['expiry_date'] = $this->extractExpiryWithAI($ocrText);
                    }

                    if (empty($request->issue_date)) {
                        $validated['issue_date'] = $this->extractIssueDateWithAI($ocrText);
                    }
                }
            }

            // Check if same document type already exists for this employee
            $existingDocument = EmployeeDocument::where('employee_id', $validated['employee_id'])
                ->where('document_type', $validated['document_type'])
                ->first();

            if ($existingDocument) {

                // Delete old file from storage
                if (!empty($existingDocument->file_url)) {

                    // Convert "/storage/employee_docs/abc.pdf"
                    // to "employee_docs/abc.pdf"
                    $oldPath = str_replace('/storage/', '', $existingDocument->file_url);

                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }

                // Delete old database record
                $existingDocument->delete();
            }

            $doc = EmployeeDocument::create($validated);
Log::info($validated);

            

            // ==========================================
        // ADD NOTIFICATION LOGIC HERE
        // ==========================================
        try {
            // Employee details fetch karein taaki naam dikha sakein
            $employee = \App\Models\Employee\Employee::find($request->employee_id);
            $empName = $employee ? $employee->first_name . ' ' . $employee->last_name : 'An Employee';

            // Custom dynamic message
            $aiStatus = $doc->verified_by_ai === 'yes' ? ' (AI Verified)' : ' (Needs Manual Verification)';
            
            // NotificationService::sendToOrganizationRoles(
            //     $request->organization_id,
            //     ['superadmin', 'Center Admin'], // Jinko ye dikhana hai unke roles
            //     'document_upload',
            //     'New Document Uploaded',
            //     "{$empName} has uploaded a new {$request->document_type}.{$aiStatus}",
            //     $employee->user_id ?? null, // Action creator
            //     [
            //         'document_id' => $doc->id,
            //         'employee_id' => $employee->id,
            //         'route_link' => "/employees/{$employee->id}/documents" // React frontend ke liye route hint
            //     ]
            // );

            NotificationService::sendDynamic(
                $request->organization_id,
                // (Roles ka array yahan se hata diya gaya hai)
                'document_upload',
                'New Document Uploaded',
                "{$empName} has uploaded a new {$request->document_type}.{$aiStatus}",
                $employee->user_id ?? null, // Action creator
                [
                    'document_id' => $doc->id,
                    'employee_id' => $employee->id,
                    'route_link' => "/employees/{$employee->id}/documents" // React frontend ke liye route hint
                ]
            );


        } catch (\Exception $e) {
            // Notification fail hone par main flow break nahi hona chahiye
            \Log::error('Failed to send document upload notification: ' . $e->getMessage());
        }
        // ==========================================



            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',

                'selected_type' => $request->document_type,
                'detected_type' => $verification['predicted_type'],
                'detected_type_confidence' => $verification['confidence'],

                'force_save' => $forceSave,

                'verified_by_ai' => $doc->verified_by_ai,
                // 'identity_confidence' => $nameVerification['confidence'],
                // 'identity_reason' => $nameVerification['reason'],
                // 'document_name' => $nameVerification['document_name'],

                // 'document_dob' => $nameVerification['document_dob'],

                'data' => $doc,
                
                ], 201);


        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {

            \Log::error('Document upload failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while uploading document.',
            ], 500);
        }
    

    }

    public function update(Request $request, $id)
    {
        $doc = EmployeeDocument::findOrFail($id);
        $validated = $request->validate([
            'document_type' => 'sometimes|string|max:190',
            'file_name'     => 'sometimes|string|max:191',
            'file'          => 'nullable|file|max:10240',
            'issue_date'    => 'nullable|date',
            'expiry_date'   => 'nullable|date|after_or_equal:issue_date',
        ]);
        if ($request->hasFile('file')) {
            if ($doc->file_url) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $doc->file_url));
            }
            $validated['file_url'] = Storage::url($request->file('file')->store('employee_docs', 'public'));
        }

        $doc->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Document updated',
            'data' => $doc
        ]);
    }

    public function destroy($id)
    {
        $doc = EmployeeDocument::findOrFail($id);
        if ($doc->file_url) Storage::disk('public')->delete(str_replace('/storage/', '', $doc->file_url));
        $doc->delete();
        return response()->json(['success' => true, 'message' => 'Document deleted']);
    }

    public function changeStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_id' => 'required|exists:employee_documents,id',
            'status'      => 'required|in:approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $document = EmployeeDocument::findOrFail($request->document_id);

        if ($request->status === 'approved') {

            $document->verify = 'approved';
            $document->verified_by = auth()->id();
            $document->save();

            return response()->json([
                'success' => true,
                'message' => 'Document approved successfully.',
                'data'    => $document,
            ]);
        }

        // Rejected
        $document->verify = 'rejected';
        $document->verified_by = null;
        $document->save();

        return response()->json([
            'success' => false,
            'message' => 'Document rejected. Please contact Admin or upload a new document for verification.',
        ]);
    }

    public function byEmployee($employeeId)
    {
        $docs = EmployeeDocument::where('employee_id', $employeeId)->orderBy('issue_date', 'desc')->get();
        return response()->json(['success' => true, 'data' => $docs]);
    }

    public function updateDocumentDates(Request $request)
    {
        try {
            $validated = $request->validate([
                'document_id' => 'required|exists:employee_documents,id',
                'issue_date'  => 'nullable|date',
                'expiry_date' => 'nullable|date',
            ]);

            $doc = EmployeeDocument::findOrFail($request->document_id);

            $doc->update([
                'issue_date'  => $request->issue_date ?? $doc->issue_date,
                'expiry_date' => $request->expiry_date ?? $doc->expiry_date,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document dates updated successfully',
                'data' => $doc
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function getEmployeeDocuments($employeeId)
    {
        try {
            $documents = EmployeeDocument::where('employee_id', $employeeId)->get();

            return response()->json([
                'success' => true,
                'data' => $documents
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function getDocumentById($documentId)
    {
        try {
            $document = EmployeeDocument::find($documentId);

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $document
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }



    public function documentTypes(Request $request)
{
    $request->validate([
        'organization_id' => 'required|exists:organizations,id'
    ]);

    $types = EmployeeDocument::where(
        'organization_id',
        $request->organization_id
    )
        ->distinct()
        ->pluck('document_type');

    return response()->json([
        'status' => true,
        'data' => $types
    ]);
}


   
    public function verifyDocument(Request $request, $id)
{
    $request->validate([
        'verify' => 'required|in:approved,rejected',
        'verified_by' => 'required|exists:employees,id'
    ]);

    $document = EmployeeDocument::findOrFail($id);

    $document->update([
        'verify' => $request->verify,
        'verified_by' => $request->verified_by
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Document verification updated successfully.',
        'data' => $document
    ]);
}


public function updateExpiryDate(Request $request, $id)
{
    $request->validate([
        'expiry_date' => 'nullable|date'
    ]);

    $document = EmployeeDocument::findOrFail($id);

    $document->update([
        'expiry_date' => $request->expiry_date
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Expiry date updated successfully.',
        'data' => $document
    ]);
}


  public function stats(Request $request)
{
    $request->validate([
        'organization_id' => 'required|exists:organizations,id'
    ]);

    $query = EmployeeDocument::where(
        'organization_id',
        $request->organization_id
    );

    return response()->json([
        'status' => true,
        'data' => [
            'total_documents' => $query->count(),

            'verified_documents' => (clone $query)
                ->where('verify', 'approved')
                ->count(),

            'unverified_documents' => (clone $query)
                ->where('verify', 'rejected')
                ->count(),

            'null_verify_documents' => (clone $query)
                ->whereNull('verify')
                ->count(),

            'expired_documents' => (clone $query)
                ->whereDate('expiry_date', '<', now())
                ->count(),

            'expiring_in_30_days' => (clone $query)
                ->whereBetween(
                    'expiry_date',
                    [now(), now()->addDays(30)]
                )
                ->count(),

            'no_expiry_documents' => (clone $query)
                ->whereNull('expiry_date')
                ->count(),
        ]
    ]);
}
   

}
