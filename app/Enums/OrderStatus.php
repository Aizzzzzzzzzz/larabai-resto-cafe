<?php

namespace App\Enums;

/** Order stages (document.md §4.10). Labels and chip colours match the mockup's STATUS map. */
enum OrderStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Awaiting payment',
            self::Paid => 'Order received',
            self::Preparing => 'Preparing',
            self::Ready => 'Ready',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Voided => 'Voided',
        };
    }

    /** CSS class of the status chip. */
    public function chip(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'chip-caramel',
            self::Paid => 'chip-gcash',
            self::Preparing => 'chip-gold',
            self::Ready => 'chip-green',
            self::Completed => 'chip-muted',
            self::Cancelled, self::Voided => 'chip-red',
        };
    }

    /** Position on the customer's tracker (cancelled / voided are off the track). */
    public function rank(): ?int
    {
        return match ($this) {
            self::AwaitingPayment => 0,
            self::Paid => 1,
            self::Preparing => 2,
            self::Ready => 3,
            self::Completed => 4,
            default => null,
        };
    }
}
