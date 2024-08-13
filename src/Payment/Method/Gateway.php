<?php

namespace Snowdog\Hyva\Checkout\PayNow\Payment\Method;

use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Magento\Checkout\Model\Session;
use Magento\Quote\Api\CartRepositoryInterface;
use Magewirephp\Magewire\Component;
use Paynow\PaymentGateway\Helper\GDPRHelper;
use Paynow\PaymentGateway\Helper\PaymentMethodsHelper;

class Gateway extends Component implements EvaluationInterface
{
    public ?string $method = '';

    public function __construct(
        private readonly GDPRHelper $gdpr,
        private readonly PaymentMethodsHelper $methods,
        private readonly Session $checkoutSession,
        private readonly CartRepositoryInterface $quoteRepository,
    ) {
    }

    public function getGdprNotices(): array
    {
        return $this->gdpr->getNotices();
    }

    public function mount()
    {
        $data = $this->checkoutSession->getQuote()->getPayment()->getAdditionalInformation();
        if (isset($data['payment_method_id'])) {
            $this->method = $data['payment_method_id'];
        }
    }

    public function getMethods(): array
    {
        $grandTotal = $this->checkoutSession->getQuote()->getGrandTotal();
        $currencyCode = $this->checkoutSession->getQuote()->getCurrency()->getQuoteCurrencyCode();

        if ($this->id = 'checkout.payment.method.paynow_pbl_gateway') {
            return $this->methods->getPblPaymentMethods($currencyCode, $grandTotal);
        } else {
            return $this->methods->getAvailable($currencyCode, $grandTotal);
        }
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if (!in_array(
            $this->checkoutSession->getQuote()->getPayment()->getMethod(),
            ['paynow_pbl_gateway', 'paynow_gateway']
        )) {
            return $resultFactory->createSuccess();
        }

        if (empty($this->method)) {
            return $resultFactory->createBlocking(__('Payment method not selected'));
        }

        $quote = $this->checkoutSession->getQuote();
        $quote->getPayment()->setAdditionalInformation('payment_method_id', $this->method);
        $this->quoteRepository->save($quote);

        return $resultFactory->createSuccess();
    }
}