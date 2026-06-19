<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = ['name', 'description', 'late_threshold_minutes'];

    public function attendanceSessions()
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function classSchedules()
    {
        return $this->hasMany(ClassSchedule::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class)->withTimestamps();
    }

    /** 担当教員（複数可） */
    public function teachers()
    {
        return $this->belongsToMany(User::class, 'course_teacher')->withTimestamps();
    }
}
