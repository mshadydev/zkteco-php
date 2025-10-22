<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Attendance Record Model
 * 
 * Eloquent model for managing ZKTeco attendance records.
 * 
 * @property int $id
 * @property string $user_id
 * @property int|null $zkteco_uid
 * @property \Carbon\Carbon $timestamp
 * @property string $date
 * @property string $time
 * @property int $status
 * @property int $punch_type
 * @property string $record_hash
 * @property string|null $device_ip
 * @property string|null $device_name
 * @property \Carbon\Carbon|null $last_sync_at
 * @property bool $is_processed
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'zkteco_uid',
        'timestamp',
        'date',
        'time',
        'status',
        'punch_type',
        'record_hash',
        'device_ip',
        'device_name',
        'last_sync_at',
        'is_processed'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'date' => 'date',
        'last_sync_at' => 'datetime',
        'is_processed' => 'boolean',
    ];

    /**
     * Get the user that owns the attendance record
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'zkteco_user_id');
    }

    /**
     * Get status text
     */
    public function getStatusTextAttribute(): string
    {
        $statuses = [
            0 => 'Check-Out',
            1 => 'Check-In',
            2 => 'Break-Out',
            3 => 'Break-In',
            4 => 'OT-In',
            5 => 'OT-Out'
        ];

        return $statuses[$this->status] ?? 'Unknown';
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope: Filter by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Only check-in records
     */
    public function scopeCheckIns($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope: Only check-out records
     */
    public function scopeCheckOuts($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Scope: Unprocessed records
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('is_processed', false);
    }

    /**
     * Scope: Recent records (today)
     */
    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    /**
     * Scope: This month records
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year);
    }

    /**
     * Create a unique record hash
     */
    public static function createRecordHash($userId, $timestamp, $status): string
    {
        return md5($userId . $timestamp . $status);
    }

    /**
     * Get daily attendance summary for a user
     */
    public static function getDailySummary($userId, $date)
    {
        return static::forUser($userId)
            ->whereDate('date', $date)
            ->orderBy('timestamp')
            ->get()
            ->groupBy('status');
    }

    /**
     * Get working hours for a user on a specific date
     */
    public static function getWorkingHours($userId, $date)
    {
        $records = static::forUser($userId)
            ->whereDate('date', $date)
            ->orderBy('timestamp')
            ->get();

        if ($records->isEmpty()) {
            return null;
        }

        $checkIn = $records->where('status', 1)->first();
        $checkOut = $records->where('status', 0)->last();

        if (!$checkIn || !$checkOut) {
            return null;
        }

        $workingMinutes = $checkOut->timestamp->diffInMinutes($checkIn->timestamp);
        
        return [
            'check_in' => $checkIn->timestamp,
            'check_out' => $checkOut->timestamp,
            'working_minutes' => $workingMinutes,
            'working_hours' => round($workingMinutes / 60, 2),
        ];
    }
}