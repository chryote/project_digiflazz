<?php

namespace App\Enums;

enum CustomerBehavior: string
{
    case PREPAID_INQUIRY = 'prepaid_inquiry';
    case SUCCESS_1_BILL = 'success_1_bill';
    case SUCCESS_2_BILLS = 'success_2_bills';
    case INQUIRY_FAILED = 'inquiry_failed';
    case PAYMENT_FAILED = 'payment_failed';
    case PENDING_CALLBACK_SUCCESS_1 = 'pending_callback_success_1';
    case PENDING_CALLBACK_SUCCESS_2 = 'pending_callback_success_2';
    case PENDING_CALLBACK_FAILED = 'pending_callback_failed';
}
