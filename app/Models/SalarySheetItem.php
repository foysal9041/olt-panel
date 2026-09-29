<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalarySheetItem extends Model
{
    protected $fillable = [
        'salary_sheet_id', 'employee_id', 'emp_code', 'name', 'designation',
        'salary', 'house_rent', 'bonus', 'advance', 'deduction', 'net',
        'transaction_id', 'sort',
    ];

    protected $casts = [
        'salary' => 'float',
        'house_rent' => 'float',
        'bonus' => 'float',
        'advance' => 'float',
        'deduction' => 'float',
        'net' => 'float',
    ];

    protected static function booted(): void
    {
        // Net Pay = Salary + House Rent + Bonus − Advance − Deduction
        static::saving(function (SalarySheetItem $item) {
            $item->net = round($item->salary + $item->house_rent + $item->bonus - $item->advance - $item->deduction, 2);
        });
    }

    public function sheet()
    {
        return $this->belongsTo(SalarySheet::class, 'salary_sheet_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
