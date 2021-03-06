<?php

namespace Etbag\TrxpsPayments\Event;

use Etbag\TrxpsPayments\Api\Resources\Order;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

class PaymentPageFailEvent extends Event
{
    public const EVENT_NAME = 'trxps.payment.page.fail';

    /**
     * @var Context
     */
    private $context;

    /**
     * @var OrderEntity|null
     */
    private $shopwareOrder;

    /**
     * @var Order|null
     */
    private $trxpsOrder;

    /**
     * @var string|null
     */
    private $salesChannelId;

    /**
     * @var string|null
     */
    private $redirectUrl;

    public function __construct(
        Context $context,
        ?OrderEntity $shopwareOrder = null,
        $trxpsOrder = null,
        ?string $salesChannelId = null,
        ?string $redirectUrl = null
    )
    {
        $this->context = $context;
        $this->shopwareOrder = $shopwareOrder;
        $this->trxpsOrder = $trxpsOrder;
        $this->salesChannelId = $salesChannelId;
        $this->redirectUrl = $redirectUrl;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getShopwareOrder(): ?OrderEntity
    {
        return $this->shopwareOrder;
    }

    public function getTrxpsOrder()
    {
        return $this->trxpsOrder;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }
}