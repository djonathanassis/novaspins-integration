<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $wallet_id
 * @property string $amount
 * @property TransactionType $type
 * @property TransactionStatus $status
 * @property-read Wallet|null $wallet
 * @property-read Transaction|null $original
 */
class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'provider_transaction_id',
        'type',
        'amount',
        'status',
        'original_transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'type' => TransactionType::class,
        'status' => TransactionStatus::class,
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_transaction_id');
    }
}
