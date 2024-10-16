<?php

declare(strict_types=1);

namespace Snowdog\Hyva\Checkout\PayNow\Plugin;

use Paynow\PaymentGateway\Helper\PaymentMethodsHelper;

class PaymentMethodsHelperPlugin
{
    public function afterGetDigitalWalletsPaymentMethods(PaymentMethodsHelper $subject, ?array $result): array
    {
        if (is_null($result)) {
            return [];
        }

        return $result;
    }

    public function afterGetPblPaymentMethods(PaymentMethodsHelper $subject, ?array $result): array
    {
        if (is_null($result)) {
            return [];
        }

        return $result;
    }
}
