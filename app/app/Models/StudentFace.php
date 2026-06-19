<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFace extends Model
{
    protected $fillable = ['student_id', 'image_path', 'label'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
