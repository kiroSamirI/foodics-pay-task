<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'date',
        'amount',
        'metadata',
        'client_id'
    ];

    protected $casts = [
        'date' => 'datetime',
        'amount' => 'decimal:2',
        'metadata' => 'json'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
