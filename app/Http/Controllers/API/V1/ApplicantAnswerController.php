<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ApplicantAnswer;


class ApplicantAnswerController extends Controller
{
     // Submit Answer
  public function store(Request $request)
{
    $request->validate([
        'question_id' => 'required|exists:interview_questions,id',
        'applicant_id' => 'required|integer',
        'answer' => 'required'
    ]);

    $data = ApplicantAnswer::create([
        'question_id' => $request->question_id,
        'applicant_id' => $request->applicant_id,
        'answer' => $request->answer,
        'rating' => 0
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Answer submitted',
        'data' => $data
    ]);
}

    // Get All Answers
public function index()
{
    $answers = ApplicantAnswer::with('question')->latest()->get();

    return response()->json([
        'status' => true,
        'data' => $answers
    ]);
}

    // Update Rating
  public function updateRating(Request $request, $id)
{
    $request->validate([
        'rating' => 'required|integer|min:1|max:5'
    ]);

    $answer = ApplicantAnswer::findOrFail($id);

    $answer->update([
        'rating' => $request->rating
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Rating updated',
        'data' => $answer
    ]);
    
}

public function getAverageRating($applicant_id)
{
    $avg = ApplicantAnswer::where('applicant_id', $applicant_id)
            ->avg('rating');

    return response()->json([
        'status' => true,
        'applicant_id' => $applicant_id,
        'average_rating' => round($avg, 2)
    ]);
}

    // Delete Answer
 public function destroy($id)
{
    ApplicantAnswer::findOrFail($id)->delete();

    return response()->json([
        'status' => true,
        'message' => 'Deleted'
    ]);
}

    public function getByApplicant($applicant_id)
{
    $answers = ApplicantAnswer::with('question')
                ->where('applicant_id', $applicant_id)
                ->get();

    return response()->json([
        'status' => true,
        'data' => $answers
    ]);
}
}
