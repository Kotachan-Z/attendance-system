<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Student extends Authenticatable
{
    protected $fillable = ['name', 'student_number', 'class_name', 'face_image_path', 'withdrawn_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'     => 'hashed',
            'withdrawn_at' => 'datetime',
        ];
    }

    /** 在籍中（退学していない）の学生だけに絞る */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('withdrawn_at');
    }

    /** 退学済みか */
    public function isWithdrawn(): bool
    {
        return ! is_null($this->withdrawn_at);
    }

    /** 組（クラス）が割り当て済みか */
    public function hasClass(): bool
    {
        return filled($this->class_name);
    }

    /** ログイン用パスワードが発行済みか */
    public function canLogin(): bool
    {
        return filled($this->password) && ! $this->isWithdrawn();
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function faces()
    {
        return $this->hasMany(StudentFace::class);
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class)->withTimestamps();
    }
}
