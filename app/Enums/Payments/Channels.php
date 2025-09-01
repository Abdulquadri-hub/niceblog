<?php

namespace App\Enums\Payments;

Enum Channels:string
{
    case BANK_TRANSFER = "bank_transfer";
    case CARD = "card";
    case BANK = "bank";
    case MOBILE_MONEY = "mobile_money";
    case USSD = "ussd";
    case EFT = "eft";
    case APPLE_PAY = "apple_pay";
    case QR = "qr";
}
