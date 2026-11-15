<?php

namespace App\Enums;

enum DigiflazzResponseCode: string
{
    case SUCCESS = '00';
    case PENDING = '03';
    case CUSTOMER_NOT_FOUND = '40';
    case PAYMENT_FAILED = '41';
    case INVALID_SIGNATURE = '42';
    case INSUFFICIENT_BALANCE = '43';
    case DUPLICATE_REF_ID = '44';
    case SYSTEM_ERROR = '99';
}
