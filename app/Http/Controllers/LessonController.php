<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\major;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $major = $user->majors_majors_id;
        // dd($major);
        $lesson = Lesson::where('lesson_majors_id', $major)->get()->map(function ($item) {
            return [
                'lesson_id' => $item->lesson_id,
                'lesson_code' => $item->lesson_code,
                'lesson_name' => $item->lesson_name
            ];
        });
        // dd($lesson);
        if ($lesson) {
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'lesson' => $lesson
            ], 200);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'data not found'
            ], 400);
        }
        //   dd($lesson);
    }
}
