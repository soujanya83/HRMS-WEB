<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InterviewQuestion;


class InterviewQuestionController extends Controller
{
     // Add Question
    public function store(Request $request)
    {
        $request->validate([
            'question' => 'required'
        ]);

        $question = InterviewQuestion::create([
            'question' => $request->question
        ]);

        return response()->json(['status' => true, 'data' => $question]);
    }

    // Get All Questions
    public function index()
    {
        $questions = InterviewQuestion::latest()->get();

        return response()->json(['status' => true, 'data' => $questions]);
    }

    // Update Question
    public function update(Request $request, $id)
    {
        $question = InterviewQuestion::findOrFail($id);

        $question->update([
            'question' => $request->question
        ]);

        return response()->json(['status' => true, 'message' => 'Updated']);
    }

    // Delete Question
    public function destroy($id)
    {
        InterviewQuestion::findOrFail($id)->delete();

        return response()->json(['status' => true, 'message' => 'Deleted']);
    }
}
