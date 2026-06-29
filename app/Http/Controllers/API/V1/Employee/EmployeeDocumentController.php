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

class EmployeeDocumentController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => EmployeeDocument::with('employee')->orderBy('issue_date', 'desc')->get()
        ]);
    }

    public function show($id)
    {
        $doc = EmployeeDocument::with('employee')->findOrFail($id);
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

            $forceSave = filter_var($request->force_save, FILTER_VALIDATE_BOOLEAN);

            $verification = [
                'match' => false,
                'predicted_type' => null,
                'confidence' => 0,
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

                    // AI verification passed
                    if (
                        $verification['match'] &&
                        $verification['confidence'] >= 90
                    ) {
                        $validated['verified_by_ai'] = 'yes';
                    }

                    // AI verification failed
                    else {

                        if (!$forceSave) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Uploaded document does not match selected document type or confidence is below 90%.',

                                'selected_type' => $request->document_type,
                                'detected_type' => $verification['predicted_type'],
                                'detected_type_confidence' => $verification['confidence'],

                                'force_save' => false,
                            ], 422);
                        }

                        // Force save enabled:
                        // verify = pending
                        // verified_by = null
                        // verified_by_ai = no
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

            $doc = EmployeeDocument::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',

                'selected_type' => $request->document_type,
                'detected_type' => $verification['predicted_type'],
                'detected_type_confidence' => $verification['confidence'],

                'force_save' => $forceSave,

                'verified_by_ai' => $doc->verified_by_ai,

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

}
