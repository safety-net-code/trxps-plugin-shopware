<?php

namespace Etbag\TrxpsPayments\Service;

use Etbag\TrxpsPayments\Api\Exceptions\ApiException;
use Etbag\TrxpsPayments\Api\TrxpsApiClient;
use Etbag\TrxpsPayments\Api\Resources\Method;
use Etbag\TrxpsPayments\Api\Resources\MethodCollection;
use Etbag\TrxpsPayments\Handler\Method\BankTransferPayment;
use Etbag\TrxpsPayments\Handler\Method\CreditCardPayment;
use Etbag\TrxpsPayments\Handler\Method\iDealPayment;
use Etbag\TrxpsPayments\Handler\Method\PayDirektPayment;
use Etbag\TrxpsPayments\Handler\Method\PayPalPayment;
use Etbag\TrxpsPayments\Handler\Method\SofortPayment;
use Etbag\TrxpsPayments\Handler\Method\TrxpsPayment;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

class PaymentMethodService
{
    /** @var MediaService */
    private $mediaService;

    /** @var EntityRepositoryInterface */
    private $paymentRepository;

    /** @var PluginIdProvider */
    private $pluginIdProvider;

    /** @var EntityRepositoryInterface */
    private $mediaRepository;

    /** @var string */
    private $className;

    /**
     * PaymentMethodHelper constructor.
     *
     * @param MediaService              $mediaService
     * @param EntityRepositoryInterface $mediaRepository
     * @param EntityRepositoryInterface $paymentRepository
     * @param PluginIdProvider          $pluginIdProvider
     * @param null                      $className
     */
    public function __construct(
        MediaService $mediaService,
        EntityRepositoryInterface $mediaRepository,
        EntityRepositoryInterface $paymentRepository,
        PluginIdProvider $pluginIdProvider,
        $className = null
    )
    {
        $this->mediaService = $mediaService;
        $this->mediaRepository = $mediaRepository;
        $this->paymentRepository = $paymentRepository;
        $this->pluginIdProvider = $pluginIdProvider;
        $this->className = $className;
    }

    /**
     * Returns the payment repository.
     *
     * @return EntityRepositoryInterface
     */
    public function getRepository(): EntityRepositoryInterface
    {
        return $this->paymentRepository;
    }

    /**
     * Sets the classname.
     *
     * @param string $className
     *
     * @return PaymentMethodService
     */
    public function setClassName(string $className): self
    {
        $this->className = $className;
        return $this;
    }

    /**
     * @param Context $context
     */
    public function addPaymentMethods(Context $context) : void
    {
        // Get the plugin ID
        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass($this->className, $context);

        // Variables
        $paymentData = [];
        $paymentMethods = $this->getPaymentMethods($context);

        foreach ($paymentMethods as $paymentMethod) {
            // Upload icon to the media repository
            $mediaId = $this->getMediaId($paymentMethod, $context);

            // Build array of payment method data
            $paymentMethodData = [
                'handlerIdentifier' => $paymentMethod['handler'],
                'name' => $paymentMethod['description'],
                'description' => '',
                'pluginId' => $pluginId,
                'mediaId' => $mediaId,
                'afterOrderEnabled' => true,
                'customFields' => [
                    'trxps_payment_method_name' => $paymentMethod['name'],
                ],
            ];

            // Get existing payment method so we can update it by it's ID
            try {
                $existingPaymentMethodId = $this->getPaymentMethodId(
                    $paymentMethodData['handlerIdentifier'],
                    $paymentMethodData['name']
                );
            } catch (InconsistentCriteriaIdsException $e) {
                // On error, we assume the payment method doesn't exist
            }

            if (isset($existingPaymentMethodId) && $existingPaymentMethodId !== null) {
                $paymentMethodData['id'] = $existingPaymentMethodId;
            }

            // Add payment method data to array of payment data
            $paymentData[] = $paymentMethodData;
        }

        // Insert or update payment data
        if (count($paymentData)) {
            $this->paymentRepository->upsert($paymentData, $context);
        }
    }

    /**
     * Activate payment methods in Shopware, based on Trxps.
     *
     * @param TrxpsApiClient $apiClient
     * @param Context         $context
     *
     * @throws ApiException
     */
    public function activatePaymentMethods(TrxpsApiClient $apiClient, Context $context): void
    {
        /** @var MethodCollection $methods */
        $methods = $apiClient->methods->allActive();

        /** @var array $paymentMethods */
        $paymentMethods = $this->getPaymentMethods();

        $handlers = [];

        if ($methods->count) {
            /** @var Method $method */
            foreach ($methods as $method) {
                foreach ($paymentMethods as $paymentMethod) {
                    if ($paymentMethod['name'] === $method->id) {
                        $handlers[] = [
                            'class' => $paymentMethod['handler'],
                            'name' => $paymentMethod['description'],
                        ];
                    }
                }
            }
        }

        if (!empty($handlers)) {
            foreach ($handlers as $handler) {
                /** @var string|null $paymentMethodId */
                $paymentMethodId = $this->getPaymentMethodId($handler['class'], $handler['name']);

                /** @var PaymentMethodEntity $paymentMethod */
                $paymentMethod = null;

                if ((string) $paymentMethodId !== '') {
                    $this->activatePaymentMethod($paymentMethodId, true, $context);
                }
            }
        }
    }

    /**
     * Activates a payment method in Shopware
     *
     * @param string       $paymentMethodId
     * @param bool         $active
     * @param Context|null $context
     *
     * @return EntityWrittenContainerEvent
     */
    public function activatePaymentMethod(
        string $paymentMethodId,
        bool $active = true,
        Context $context = null
    ): EntityWrittenContainerEvent
    {
        return $this->paymentRepository->upsert([[
            'id' => $paymentMethodId,
            'active' => $active
        ]], $context ?? Context::createDefaultContext());
    }

    /**
     * Get payment method by ID.
     *
     * @param $id
     * @return PaymentMethodEntity
     * @throws InconsistentCriteriaIdsException
     */
    public function getPaymentMethodById($id) : ?PaymentMethodEntity
    {
        // Fetch ID for update
        $paymentCriteria = new Criteria();
        $paymentCriteria->addFilter(new EqualsFilter('id', $id));

        // Get payment methods
        $paymentMethods = $this->paymentRepository->search($paymentCriteria, Context::createDefaultContext());

        if ($paymentMethods->getTotal() === 0) {
            return null;
        }

        return $paymentMethods->first();
    }

    /**
     * Get payment method ID by name.
     *
     * @param $handlerIdentifier
     * @param $name
     *
     * @return string|null
     */
    private function getPaymentMethodId($handlerIdentifier, $name) : ?string
    {
        // Fetch ID for update
        $paymentCriteria = new Criteria();
        $paymentCriteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));
        $paymentCriteria->addFilter(new EqualsFilter('name', $name));

        // Get payment IDs
        $paymentIds = $this->paymentRepository->searchIds($paymentCriteria, Context::createDefaultContext());

        if ($paymentIds->getTotal() === 0) {
            return null;
        }

        return $paymentIds->getIds()[0];
    }

    /**
     * Get an array of available payment methods from the Trxps API.
     *
     * @param Context|null $context
     * @return array
     */
    private function getPaymentMethods(?Context $context = null) : array
    {
        // Variables
        $paymentMethods = [];
        $availableMethods = $this->getPaymentHandlers();

        // Add payment methods to array
        if ($availableMethods !== null) {
            foreach ($availableMethods as $availableMethod) {
                $paymentMethods[] = [
                    'name' => constant($availableMethod . '::PAYMENT_METHOD_NAME'),
                    'description' => constant($availableMethod . '::PAYMENT_METHOD_DESCRIPTION'),
                    'handler' => $availableMethod,
                ];
            }
        }

        return $paymentMethods;
    }

    /**
     * Returns an array of payment handlers.
     *
     * @return array
     */
    public function getPaymentHandlers()
    {
        return [
            // BankTransferPayment::class,
            // CreditCardPayment::class,
            // iDealPayment::class,
            // PayDirektPayment::class,
            // PayPalPayment::class,
            // SofortPayment::class,
            TrxpsPayment::class,
        ];
    }

    /**
     * Retrieve the icon from the database, or add it.
     *
     * @param array   $paymentMethod
     * @param Context $context
     *
     * @return string
     */
    private function getMediaId(array $paymentMethod, Context $context): string
    {
        /** @var string $mediaId */
        $mediaId = '';

        /** @var string $fileName */
        $fileName = $paymentMethod['name'] . '-icon';

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $fileName));

        /** @var MediaCollection $icons */
        $icons = $this->mediaRepository->search($criteria, $context);

        if ($icons->count() && $icons->first() !== null) {
            $mediaId = $icons->first()->getId();
        } else {
            // Add icon to the media library

            $iconBlob = "foo";

            $iconMime = 'image/svg+xml';
            $iconExt = 'svg';
            $mediaId = $this->mediaService->saveFile(
                $iconBlob,
                $iconExt,
                $iconMime,
                $fileName,
                $context,
                'Trxps Payments - Icons',
                null,
                false
            );
        }

        return $mediaId;
    }
}
