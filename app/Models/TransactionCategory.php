<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class TransactionCategory extends Model
{
    use LogsActivity;

    /**
     * Where a head's entries count on the monthly Net Profit sheet. Heads
     * left unset count as "others" income / cost.
     */
    public const PL_GROUPS = [
        'income' => ['other' => 'Others Income', 'none' => 'Not counted (petty cash in, loans, transfers)'],
        'expense' => ['fixed' => 'Fixed Cost', 'other' => 'Others Cost', 'none' => 'Not counted'],
    ];

    protected $fillable = [
        'name',
        'type',
        'pl_group',
    ];

    public function plGroup(): string
    {
        return isset(self::PL_GROUPS[$this->type][$this->pl_group]) ? $this->pl_group : 'other';
    }

    public function plGroupLabel(): string
    {
        return self::PL_GROUPS[$this->type][$this->plGroup()] ?? '';
    }

    /** The head's name in the interface language (names are often Bangla). */
    public function displayName(): string
    {
        return \App\Support\Ui::t($this->name);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
