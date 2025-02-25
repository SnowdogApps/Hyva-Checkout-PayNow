<?php

declare(strict_types=1);

namespace Snowdog\Hyva\Checkout\PayNow\Payment\Method;

use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Magento\Checkout\Model\Session;
use Magento\Quote\Api\CartRepositoryInterface;
use Magewirephp\Magewire\Component;
use Paynow\PaymentGateway\Helper\GDPRHelper;
use Paynow\PaymentGateway\Helper\PaymentMethodsHelper;

class PayPo extends Component implements EvaluationInterface
{
    public function __construct(
        private readonly GDPRHelper $gdpr
    ) {
    }

    public function getGdprNotices(): array
    {
        return $this->gdpr->getNotices();
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        $quote = $this->checkoutSession->getQuote();
        if ($quote->getPayment()->getMethod() != 'paynow_paypo_gateway') {
            return $resultFactory->createSuccess();
        }

        return $resultFactory->createSuccess();
    }
}
