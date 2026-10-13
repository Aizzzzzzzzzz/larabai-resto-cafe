<?php

namespace App\Enums;

/** Where an order came from: a customer's QR scan, or the cashier's point of sale. */
enum OrderSource: string
{
    case Qr = 'qr';
    case Pos = 'pos';
}
