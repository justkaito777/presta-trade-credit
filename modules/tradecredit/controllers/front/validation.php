<?php

declare(strict_types=1);

use TradeCredit\Service\TradeCreditService;

class TradeCreditValidationModuleFrontController extends ModuleFrontController
{
    /**
     * Process payment validation
     */
    public function postProcess(): void
    {
        $cart = $this->context->cart;
        $customer = new Customer((int)$cart->id_customer);

        if (
            !$cart->id
            || $cart->id_customer == 0
            || $cart->id_address_delivery == 0
            || $cart->id_address_invoice == 0
            || !$this->module->active
        ) {
            Tools::redirect('index.php?controller=order&step=1');
            return;
        }

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
            return;
        }

        // Check credit availability
        $service = new TradeCreditService();
        $cartTotal = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $availableCredit = $service->getAvailableCredit((int)$customer->id);

        if ($availableCredit < $cartTotal) {
            $this->errors[] = $this->module->getTranslator()->trans(
                'Niewystarczający kredyt kupiecki. Dostępny kredyt: %credit% PLN, kwota zamówienia: %total% PLN.',
                [
                    '%credit%' => number_format($availableCredit, 2, ',', ' '),
                    '%total%' => number_format($cartTotal, 2, ',', ' '),
                ],
                'Modules.Tradecredit.Shop'
            );
            $this->redirectWithNotifications('index.php?controller=order&step=1');
            return;
        }

        // Validate order
        $currency = $this->context->currency;
        $total = $cart->getOrderTotal(true, Cart::BOTH);

        $this->module->validateOrder(
            (int)$cart->id,
            (int)Configuration::get('PS_OS_PAYMENT'),
            $total,
            $this->module->displayName,
            null,
            [],
            (int)$currency->id,
            false,
            $customer->secure_key
        );

        Tools::redirect(
            'index.php?controller=order-confirmation&id_cart=' . (int)$cart->id
            . '&id_module=' . (int)$this->module->id
            . '&id_order=' . (int)$this->module->currentOrder
            . '&key=' . $customer->secure_key
        );
    }
}
