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

class Blik extends Component implements EvaluationInterface
{
    public ?string $blikCode = '';
    public function __construct(
        private readonly GDPRHelper $gdpr,
        private readonly PaymentMethodsHelper $methods,
        private readonly Session $checkoutSession,
        private readonly CartRepositoryInterface $quoteRepository,
    ) {
    }

    public function mount()
    {
        $data = $this->checkoutSession->getQuote()->getPayment()->getAdditionalInformation();
        if (isset($data['blik_code'])) {
            $this->blikCode = $data['blik_code'];
        }
    }

    public function getGdprNotices(): array
    {
        return $this->gdpr->getNotices();
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        $quote = $this->checkoutSession->getQuote();
        if ($quote->getPayment()->getMethod() != 'paynow_blik_gateway') {
            return $resultFactory->createSuccess();
        }

        if (empty($this->blikCode)) {
            $quote->getPayment()->setAdditionalInformation('blik_code', $this->blikCode);
            $this->quoteRepository->save($quote);

            return $resultFactory->createErrorMessageEvent()
            ->withCustomEvent('payment:method:error')
            ->withMessage('Enter BLIK Code.');
        }

        $grandTotal = $this->checkoutSession->getQuote()->getGrandTotal();
        $currencyCode = $this->checkoutSession->getQuote()->getCurrency()->getQuoteCurrencyCode();
        $blikPaymentMethod = $this->methods->getBlikPaymentMethod($currencyCode, $grandTotal);

        $quote = $this->checkoutSession->getQuote();
        $quote->getPayment()->setAdditionalInformation('payment_method_id',$blikPaymentMethod->getId());
        $quote->getPayment()->setAdditionalInformation('blik_code', $this->blikCode);
        $this->quoteRepository->save($quote);

        return $resultFactory->createSuccess();
    }
}