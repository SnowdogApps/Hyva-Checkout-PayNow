<?php

namespace Snowdog\Hyva\Checkout\PayNow\Payment\Method;

use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Magewirephp\Magewire\Component;
use Paynow\PaymentGateway\Helper\GDPRHelper;

class DigitalWallet  extends Component implements EvaluationInterface
{
    public function __construct(private readonly GDPRHelper $gdpr)
    {
    }

    public function getGdprNotices(): array
    {
        return $this->gdpr->getNotices();
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        // TODO: Implement evaluateCompletion() method.
        return $resultFactory->createSuccess();
    }
}