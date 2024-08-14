<?php

namespace Snowdog\Hyva\Checkout\PayNow\Payment\Method;

use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Quote\Api\CartRepositoryInterface;
use Magewirephp\Magewire\Component;
use Paynow\PaymentGateway\Helper\GDPRHelper;
use Paynow\PaymentGateway\Helper\PaymentMethodsHelper;

class Card extends Component implements EvaluationInterface
{
    public ?string $token = '';

    public ?string $fingerprint = '';

    public function __construct(
        private readonly GDPRHelper              $gdpr,
        private readonly PaymentMethodsHelper    $methods,
        private readonly Session                 $checkoutSession,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly AssetRepository         $assetRepository
    ) {
    }

    public function mount()
    {
        $data = $this->checkoutSession->getQuote()->getPayment()->getAdditionalInformation();
        if (isset($data['payment_method_token'])) {
            $this->token = $data['payment_method_token'];
        }
        $fingerprintSession = $this->checkoutSession->getData('paynow_fingerprint');
        if (!empty($fingerprintSession)) {
            $this->fingerprint = $fingerprintSession;
        }
    }
    public function getGdprNotices(): array
    {
        return $this->gdpr->getNotices();
    }

    public function getDefaultCardImagePath(): string
    {
        return $this->assetRepository->getUrl('Paynow_PaymentGateway::images/card-default.svg');
    }

    public function getInstruments(): array
    {
        $instruments = [];

        $grandTotal = $this->checkoutSession->getQuote()->getGrandTotal();
        $currencyCode = $this->checkoutSession->getQuote()->getCurrency()->getQuoteCurrencyCode();
        $cardPaymentMethod = $this->methods->getCardPaymentMethod($currencyCode, $grandTotal);
        if ($cardPaymentMethod) {
            foreach ($cardPaymentMethod->getSavedInstruments() ?? [] as $savedInstrument) {
                $instruments[] = [
                    'token' => $savedInstrument->getToken(),
                    'isExpired' => $savedInstrument->isExpired(),
                    'image' => $savedInstrument->getImage(),
                    'brand' => $savedInstrument->getBrand(),
                    'name' => $savedInstrument->getName(),
                    'expirationDate' => $savedInstrument->getExpirationDate(),
                ];
            }
        }

        return $instruments;
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if ($this->checkoutSession->getQuote()->getPayment()->getMethod() != 'paynow_card_gateway') {
            return $resultFactory->createSuccess();
        }

        if (empty($this->fingerprint)) {
            return $resultFactory->createBlocking(__('Missing browser fingerprint'));
        }

        $instruments = $this->getInstruments();

        foreach($instruments as $instrument) {
            if ($instrument['token'] == $this->token && $instrument['isExpired']) {
                return $resultFactory->createBlocking(__('This card has expired'));
            }
        }

        $grandTotal = $this->checkoutSession->getQuote()->getGrandTotal();
        $currencyCode = $this->checkoutSession->getQuote()->getCurrency()->getQuoteCurrencyCode();
        $cardPaymentMethod = $this->methods->getCardPaymentMethod($currencyCode, $grandTotal);

        if ($cardPaymentMethod) {
            $quote = $this->checkoutSession->getQuote();
            $quote->getPayment()->setAdditionalInformation('payment_method_id', 1234); // $cardPaymentMethod->getId());
            $quote->getPayment()->setAdditionalInformation('payment_method_token', $this->token);
            $quote->getPayment()->setAdditionalInformation('payment_method_fingerprint', $this->fingerprint);
            $this->quoteRepository->save($quote);
        } else {
            return $resultFactory->createBlocking(__('Card payment not allowed'));
        }

        $this->checkoutSession->setData('paynow_fingerprint', $this->fingerprint);

        return $resultFactory->createSuccess();
    }
}