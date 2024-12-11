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
        private readonly GDPRHelper              $gdpr,
        private readonly PaymentMethodsHelper    $methods,
        private readonly Session                 $checkoutSession,
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

        return match ($this->id) {
            'checkout.payment.method.paynow_digital_wallet_gateway' => $this->methods->getDigitalWalletsPaymentMethods(
                $currencyCode,
                $grandTotal
            ),
            'checkout.payment.method.paynow_pbl_gateway' => $this->methods->getPblPaymentMethods(
                $currencyCode,
                $grandTotal
            ),
            default => $this->methods->getAvailable($currencyCode, $grandTotal),
        };
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if (!in_array(
            $this->checkoutSession->getQuote()->getPayment()->getMethod(),
            ['paynow_pbl_gateway', 'paynow_gateway', 'paynow_digital_wallet_gateway']
        )) {
            return $resultFactory->createSuccess();
        }

        if (empty($this->method)) {
            return $resultFactory->createErrorMessageEvent()
                ->withCustomEvent('payment:method:error')
                ->withMessage('Payment method not selected.');
        }

        $availableIds = array_column($this->getMethods(), 'id');
        if (!in_array($this->method, $availableIds)) {
            return $resultFactory->createErrorMessageEvent()
                ->withCustomEvent('payment:method:error')
                ->withMessage('Payment method not selected.');
        }

        $quote = $this->checkoutSession->getQuote();
        $quote->getPayment()->setAdditionalInformation('payment_method_id', $this->method);
        $this->quoteRepository->save($quote);

        return $resultFactory->createSuccess();
    }
}