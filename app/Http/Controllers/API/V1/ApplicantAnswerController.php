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
            'applicant_name' => 'required',
            'answer' => 'required'
        ]);

        $data = ApplicantAnswer::create($request->all());

        return response()->json(['status' => true, 'data' => $data]);
    }

    // Get All Answers
    public function index()
    {
        $answers = ApplicantAnswer::with('question')->latest()->get();

        return response()->json(['status' => true, 'data' => $answers]);
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

        return response()->json(['status' => true, 'message' => 'Rating updated']);
    }

    // Delete Answer
    public function destroy($id)
    {
        ApplicantAnswer::findOrFail($id)->delete();

        return response()->json(['status' => true, 'message' => 'Deleted']);
    }
}
