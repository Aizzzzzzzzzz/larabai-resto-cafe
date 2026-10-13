<?php

namespace App\Models;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Only App\Services\OrderWorkflow changes `status`; the payment and stock services change the rest. */
class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source' => OrderSource::class,
            'order_type' => OrderType::class,
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'subtotal' => 'float',
            'discount_amount' => 'float',
            'delivery_fee' => 'float',
            'total' => 'float',
            'stock_deducted' => 'boolean',
            'paid_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->orderBy('changed_at')->orderBy('id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function discount(): HasOne
    {
        return $this->hasOne(Discount::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Cash on delivery: goes to the kitchen unpaid and the rider brings the cash back. */
    public function isCod(): bool
    {
        return $this->order_type === OrderType::Delivery && $this->payment_method === PaymentMethod::Cash;
    }
}
