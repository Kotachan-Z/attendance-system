<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;

class ClassSchedule extends Model
{
    protected $fillable = [
        'course_id',
        'type',
        'day_of_week',
        'effective_from',
        'effective_until',
        'specific_date',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'effective_from'  => 'date',
        'effective_until' => 'date',
        'specific_date'   => 'date',
    ];

    public const DOW_LABELS = ['日', '月', '火', '水', '木', '金', '土'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function attendanceSessions()
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function dayOfWeekLabel(): string
    {
        if ($this->day_of_week === null) {
            return '';
        }
        return self::DOW_LABELS[$this->day_of_week] ?? '';
    }

    /**
     * このスケジュールが対象とする全ての日付を返す。
     * weekly: effective_from〜effective_until の該当曜日すべて
     * onetime: specific_date のみ
     *
     * @return \Carbon\Carbon[]
     */
    public function occurrenceDates(): array
    {
        if ($this->type === 'onetime') {
            return $this->specific_date ? [$this->specific_date->copy()] : [];
        }

        if (! $this->effective_from || ! $this->effective_until || $this->day_of_week === null) {
            return [];
        }

        $dates = [];
        $period = CarbonPeriod::create($this->effective_from->copy(), $this->effective_until->copy());
        foreach ($period as $date) {
            if ($date->dayOfWeek === (int) $this->day_of_week) {
                $dates[] = $date->copy();
            }
        }
        return $dates;
    }

    /**
     * 指定日のセッション予定開始/終了の Carbon を返す。
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function scheduledRangeFor(Carbon $date): array
    {
        $start = Carbon::parse($date->toDateString() . ' ' . $this->start_time);
        $end   = Carbon::parse($date->toDateString() . ' ' . $this->end_time);
        return [$start, $end];
    }
}
