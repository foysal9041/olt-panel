<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'zk_device_id',
        'employee_id',
        'device_user_id',
        'punched_at',
        'verify_mode',
        'status_code',
    ];

    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function zkDevice()
    {
        return $this->belongsTo(ZkDevice::class);
    }
}
