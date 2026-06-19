<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Support\Facades\Storage;

class StudentController extends Controller
{
    public function index()
    {
        // 退学者はカメラの認識対象から除外する（名簿・集計と方針を揃える）
        $students = Student::active()->with('faces')->get();

        return response()->json(
            $students->map(fn($s) => [
                'id'             => $s->id,
                'name'           => $s->name,
                'student_number' => $s->student_number,
                'face_images'    => $s->faces->map(fn($f) => [
                    'id'         => $f->id,
                    'label'      => $f->label,
                    'local_path' => storage_path('app/public/' . $f->image_path),
                ])->values(),
            ])
        );
    }
}
