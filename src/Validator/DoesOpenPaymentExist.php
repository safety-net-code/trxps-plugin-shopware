<?php

namespace Etbag\TrxpsPayments\Validator;


use Etbag\TrxpsPayments\Api\Resources\Payment;
use Etbag\TrxpsPayments\Api\Types\PaymentStatus;

/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class DoesOpenPaymentExist
{

    /**
     * @param Payment[] $payments
     * @return bool
     */
    public static function validate(array $payments): bool
    {
        if (count($payments) === 0) {
            return false;
        }

        $filteredPayments = array_filter($payments, function (Payment $payment) {
            return $payment->status === PaymentStatus::STATUS_OPEN;
        });

        return count($filteredPayments) > 0;
    }
}
