<?php

namespace Etbag\TrxpsPayments\Handler\Method;

use Etbag\TrxpsPayments\Handler\PaymentHandler;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class iDealPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = 'iDEAL';
    public const PAYMENT_METHOD_DESCRIPTION = 'iDEAL';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    protected function processPaymentMethodSpecificParameters(
        array $orderData,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer,
        LocaleEntity $locale
    ): array
    {
        return $orderData;
    }
}