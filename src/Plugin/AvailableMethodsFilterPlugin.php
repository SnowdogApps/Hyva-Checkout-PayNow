<?php

namespace Snowdog\Hyva\Checkout\PayNow\Plugin;

use Magento\Checkout\Model\Session;
use Magento\Quote\Api\Data\PaymentMethodInterface as PaymentMethodInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface as PaymentMethodManagementInterface;
use Paynow\Model\PaymentMethods\Type;
use Paynow\PaymentGateway\Helper\ConfigHelper;
use Paynow\PaymentGateway\Helper\PaymentMethodsHelper;
use Paynow\PaymentGateway\Model\Config\Source\PaymentMethodsToHide;

class AvailableMethodsFilterPlugin
{
    public function __construct(
        private readonly ConfigHelper         $configHelper,
        private readonly Session              $checkoutSession,
        private readonly PaymentMethodsHelper $paymentMethodsHelper
    ) {
    }

    public function afterGetList(PaymentMethodManagementInterface $subject, array $result): array
    {
        $newResult = [];

        $grandTotal = $this->checkoutSession->getQuote()->getGrandTotal();
        $currencyCode = $this->checkoutSession->getQuote()->getCurrency()->getQuoteCurrencyCode();

        foreach ($result as $method) {
            /** @var PaymentMethodInterface $method */

            switch ($method->getCode()) {
                case "paynow_gateway":
                    $isActive = $this->configHelper->isActive() &&
                        $this->configHelper->isConfigured() &&
                        !$this->configHelper->isPaymentMethodsActive();
                    break;
                case "paynow_pbl_gateway":
                    $isActive = $this->configHelper->isActive() &&
                        $this->configHelper->isConfigured() &&
                        $this->configHelper->isPaymentMethodsActive()
                        && !in_array(
                            PaymentMethodsToHide::PAYMENT_TYPE_TO_CONFIG_MAP[Type::PBL],
                            $this->configHelper->getPaymentMethodsToHide()
                        );
                    break;
                case "paynow_blik_gateway":
                    $blikPaymentMethod = $this->paymentMethodsHelper->getBlikPaymentMethod($currencyCode, $grandTotal);
                    $isActive = $this->configHelper->isActive()
                        && $this->configHelper->isConfigured()
                        && $blikPaymentMethod
                        && $blikPaymentMethod->isEnabled()
                        && !in_array(
                            PaymentMethodsToHide::PAYMENT_TYPE_TO_CONFIG_MAP[$blikPaymentMethod->getType()],
                            $this->configHelper->getPaymentMethodsToHide()
                        );
                    break;
                case "paynow_card_gateway":
                    $cardPaymentMethod = $this->paymentMethodsHelper->getCardPaymentMethod($currencyCode, $grandTotal);
                    $isActive = $this->configHelper->isActive()
                        && $this->configHelper->isConfigured()
                        && $cardPaymentMethod
                        && $cardPaymentMethod->isEnabled()
                        && !in_array(
                            PaymentMethodsToHide::PAYMENT_TYPE_TO_CONFIG_MAP[$cardPaymentMethod->getType()],
                            $this->configHelper->getPaymentMethodsToHide()
                        );
                    break;
                case "paynow_paypo_gateway":
                    $paypoPaymentMethod = $this->paymentMethodsHelper->getPaypoPaymentMethod($currencyCode, $grandTotal);
                    $isActive = $this->configHelper->isActive()
                        && $this->configHelper->isConfigured()
                        && $paypoPaymentMethod
                        && $paypoPaymentMethod->isEnabled()
                        && !in_array(
                            PaymentMethodsToHide::PAYMENT_TYPE_TO_CONFIG_MAP[$paypoPaymentMethod->getType()],
                            $this->configHelper->getPaymentMethodsToHide()
                        );
                    break;
                case "paynow_digital_wallet_gateway":
                    $isActive = $this->configHelper->isActive() &&
                        $this->configHelper->isConfigured() &&
                        $this->configHelper->isPaymentMethodsActive();
                    $paymentMethods = $this->paymentMethodsHelper->getDigitalWalletsPaymentMethods(
                        $currencyCode,
                        $grandTotal
                    );
                    foreach ($paymentMethods as $paymentMethod) {
                        if (in_array(
                            PaymentMethodsToHide::PAYMENT_TYPE_TO_CONFIG_MAP[$paymentMethod['type'] ?? ''] ?? '',
                            $this->configHelper->getPaymentMethodsToHide()
                        )) {
                            $isActive = false;
                            break;
                        }
                    }
                    break;

                default:
                    $isActive = true;
                    break;
            }
            if ($isActive) {
                $newResult[] = $method;
            }
        }

        return $newResult;
    }
}