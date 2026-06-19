<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentFace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StudentController extends Controller
{
    public function index()
    {
        // 在籍中の学生（組→氏名順）
        $students = Student::withCount(['attendanceRecords', 'faces'])
            ->active()
            ->orderByRaw("CASE WHEN class_name IS NULL OR class_name = '' THEN 1 ELSE 0 END")
            ->orderBy('class_name')
            ->orderBy('name')
            ->get();

        // 組（クラス）ごとにグループ化。未設定はまとめて最後に。
        $grouped = $students->groupBy(fn ($s) => $s->class_name ?: '');

        // 退学者（名簿からは外すが、管理者が復学できるよう別枠で表示）
        $withdrawn = Student::withCount(['attendanceRecords', 'faces'])
            ->whereNotNull('withdrawn_at')
            ->orderBy('name')
            ->get();

        return view('students.index', compact('students', 'grouped', 'withdrawn'));
    }

    /** 複数学生にまとめて組（クラス）を登録する */
    public function bulkClass(Request $request)
    {
        $validated = $request->validate([
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:students,id',
            'class_name'    => 'nullable|string|max:50',
        ]);

        $className = $validated['class_name'] !== null && $validated['class_name'] !== ''
            ? $validated['class_name']
            : null;

        $count = Student::whereIn('id', $validated['student_ids'])
            ->update(['class_name' => $className]);

        $msg = $className
            ? "{$count}名を「{$className}」に登録しました。"
            : "{$count}名を「未分類（組なし）」に変更しました。";

        return redirect()->route('students.index')->with('success', $msg);
    }

    public function create()
    {
        return view('students.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'student_number' => 'required|string|max:50|unique:students',
            'class_name'     => 'nullable|string|max:50',
            'login_password' => 'nullable|string|min:6|max:255',
            'face_images'    => 'required|array|min:1|max:10',
            'face_images.*'  => 'image|mimes:jpeg,png,jpg,heic,heif|max:20480',
            'face_labels'    => 'nullable|array',
            'face_labels.*'  => 'nullable|string|max:50',
        ]);

        $student = Student::create([
            'name'           => $validated['name'],
            'student_number' => $validated['student_number'],
            'class_name'     => $validated['class_name'] ?? null,
        ]);

        // ログイン用パスワードの初期値は「学籍番号と同じ」にしておく。
        //   管理者が明示的にパスワードを指定した場合のみ、その値で上書きする。
        $initialPassword = $student->student_number;
        if (Auth::user()?->isAdmin() && filled($validated['login_password'] ?? null)) {
            $initialPassword = $validated['login_password'];
        }
        $student->password = $initialPassword; // hashed キャストで自動ハッシュ
        $student->save();

        foreach ($request->file('face_images') as $i => $image) {
            $path  = $image->store('faces/' . $student->id, 'public');
            $label = $request->input("face_labels.{$i}") ?? '';
            StudentFace::create([
                'student_id' => $student->id,
                'image_path' => $path,
                'label'      => $label,
            ]);
        }

        // 後方互換: 1枚目を face_image_path にも保存
        $student->update(['face_image_path' => $student->faces()->first()->image_path]);

        return redirect()->route('students.index')->with('success', '学生を登録しました。');
    }

    public function show(Student $student)
    {
        $student->load('faces');
        $records = $student->attendanceRecords()
                           ->with('attendanceSession.course')
                           ->latest()
                           ->get();
        return view('students.show', compact('student', 'records'));
    }

    public function edit(Student $student)
    {
        $student->load('faces');
        return view('students.edit', compact('student'));
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'student_number' => 'required|string|max:50|unique:students,student_number,' . $student->id,
            'class_name'     => 'nullable|string|max:50',
            'login_password' => 'nullable|string|min:6|max:255',
            'withdrawn'      => 'nullable|boolean',
            'face_images'    => 'nullable|array|max:10',
            'face_images.*'  => 'nullable|image|mimes:jpeg,png,jpg,heic,heif|max:20480',
            'face_labels'    => 'nullable|array',
            'face_labels.*'  => 'nullable|string|max:50',
        ]);

        $student->update([
            'name'           => $validated['name'],
            'student_number' => $validated['student_number'],
            'class_name'     => $validated['class_name'] ?? null,
        ]);

        // パスワード再発行・退学フラグは管理者のみ
        if (Auth::user()?->isAdmin()) {
            if (filled($validated['login_password'] ?? null)) {
                $student->password = $validated['login_password'];
            }
            $student->withdrawn_at = $request->boolean('withdrawn') ? ($student->withdrawn_at ?? now()) : null;
            $student->save();
        }

        if ($request->hasFile('face_images')) {
            foreach ($request->file('face_images') as $i => $image) {
                if (!$image || !$image->isValid()) {
                    continue;
                }
                $path  = $image->store('faces/' . $student->id, 'public');
                $label = $request->input("face_labels.{$i}") ?? '';
                StudentFace::create([
                    'student_id' => $student->id,
                    'image_path' => $path,
                    'label'      => $label,
                ]);
            }
            $first = $student->faces()->first();
            if ($first) {
                $student->update(['face_image_path' => $first->image_path]);
            }
        }

        return redirect()->route('students.show', $student)->with('success', '学生情報を更新しました。');
    }

    public function destroyFace(Student $student, StudentFace $face)
    {
        abort_if($face->student_id !== $student->id, 403);
        Storage::disk('public')->delete($face->image_path);
        $face->delete();

        // face_image_path を残っている先頭に更新
        $first = $student->faces()->first();
        $student->update(['face_image_path' => $first?->image_path]);

        return back()->with('success', '写真を削除しました。');
    }

    public function destroy(Student $student)
    {
        foreach ($student->faces as $face) {
            Storage::disk('public')->delete($face->image_path);
        }
        if ($student->face_image_path) {
            Storage::disk('public')->delete($student->face_image_path);
        }
        $student->delete();
        return redirect()->route('students.index')->with('success', '学生を削除しました。');
    }
}
