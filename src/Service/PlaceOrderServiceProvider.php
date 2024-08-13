<?php

namespace Snowdog\Hyva\Checkout\PayNow\Service;

use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;
use Paynow\Model\PaymentMethods\AuthorizationType;
use Paynow\PaymentGateway\Helper\PaymentMethodsHelper;

class PlaceOrderServiceProvider extends AbstractPlaceOrderService
{
    public function __construct(
        CartManagementInterface               $cartManagement,
        private readonly RequestInterface     $request,
        private readonly UrlInterface         $urlBuilder,
        private readonly PaymentMethodsHelper $methods,

    ) {
        parent::__construct($cartManagement);
    }

    public function getRedirectUrl(Quote $quote, ?int $orderId = null): string
    {
        if ($quote->getPayment()->getMethod() == 'paynow_blik_gateway') {
            $grandTotal = $quote->getGrandTotal();
            $currencyCode = $quote->getQuoteCurrencyCode();
            $blikPaymentMethod = $this->methods->getBlikPaymentMethod($currencyCode, $grandTotal);

            if ($blikPaymentMethod->getAuthorizationType() === AuthorizationType::CODE) {
                return $this->urlBuilder->getUrl(
                    'paynow/payment/confirm',
                    ['_secure' => $this->request->isSecure()]
                );
            }
        }

        return $this->urlBuilder->getUrl('paynow/checkout/redirect', ['_secure' => $this->request->isSecure()]);
    }
}