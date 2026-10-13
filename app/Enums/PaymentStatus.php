<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Paid = 'PAID';
    case Voided = 'VOIDED';
}
