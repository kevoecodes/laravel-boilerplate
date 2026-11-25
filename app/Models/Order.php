<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const STATUSES = ['pending', 'processing', 'completed', 'cancelled'];

    /** @var list<string> */
    protected $fillable = [
        'order_number',
        'user_id',
        'product_id',
        'quantity',
        'unit_price',
        'total',
        'status',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->order_number ??= 'ORD-'.strtoupper(Str::random(8));
        });

        static::saving(function (Order $order) {
            $order->total = round((float) $order->unit_price * (int) $order->quantity, 2);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
