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
                    'Authorization' => 'Bearer ' . env('DEEPSEEK_API_KEY'),
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'   => 'required|exists:employees,id',
            'document_type' => 'required|string|max:190',
            'file_name'     => 'required|string|max:191',
            'file'          => 'required|file|mimes:jpg,jpeg,png|max:10240',
            'issue_date'    => 'nullable|date',
        ]);
        $path = $request->file('file')->store('employee_docs', 'public');
        $validated['file_url'] = Storage::url($path);
        $fileBytes = file_get_contents(storage_path('app/public/' . $path));
        $ocrText = '';
        try {
            $textract = new TextractClient([
                'region'  => env('AWS_DEFAULT_REGION'),
                'version' => 'latest',
                'credentials' => [
                    'key'    => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);

            $result = $textract->analyzeDocument([
                'Document' => [
                    'Bytes' => $fileBytes,
                ],
                'FeatureTypes' => ['FORMS'],
            ]);

            foreach ($result['Blocks'] as $block) {
                if ($block['BlockType'] === 'LINE') {
                    $ocrText .= $block['Text'] . "\n";
                }
            }
        } catch (\Throwable $e) {
            \Log::error('Textract failed', ['error' => $e->getMessage()]);
        }

        if (!$ocrText) {
            throw ValidationException::withMessages([
                'file' => 'Unable to read document text.'
            ]);
        }
         
        $expiryDate = $this->extractExpiryWithAI($ocrText);
        if (!$expiryDate) {
            throw ValidationException::withMessages([
                'expiry_date' => 'Expiry date could not be detected automatically. Please enter manually.'
            ]);
        }

        $validated['expiry_date'] = $expiryDate;
        $doc = EmployeeDocument::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'data'    => $doc
        ], 201);
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
}
