<?php

declare(strict_types=1);

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

class TradeCredit extends PaymentModule
{
    public const DEFAULT_CREDIT = 50000.00;
    public const CONFIG_DEFAULT_CREDIT = 'TRADE_CREDIT_DEFAULT_AMOUNT';

    public function __construct()
    {
        $this->name = 'tradecredit';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Kajetan Teodorczyk';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Kredyt kupiecki', [], 'Modules.Tradecredit.Admin');
        $this->description = $this->trans(
            'Moduł płatności kredytem kupieckim. Każdy klient otrzymuje domyślnie kredyt kupiecki, który jest pomniejszany przy każdym zamówieniu.',
            [],
            'Modules.Tradecredit.Admin'
        );
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];;
    }

    /**
     * Module installation
     */
    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        $installer = new \TradeCredit\Install\Installer();
        if (!$installer->install()) {
            return false;
        }

        Configuration::updateValue(self::CONFIG_DEFAULT_CREDIT, (string)self::DEFAULT_CREDIT);

        return $this->registerHook('actionCustomerAccountAdd')
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('actionOrderStatusPostUpdate')
            && $this->registerHook('actionCustomerFormBuilderModifier')
            && $this->registerHook('actionAfterCreateCustomerFormHandler')
            && $this->registerHook('actionAfterUpdateCustomerFormHandler');
    }

    /**
     * Module uninstallation
     */
    public function uninstall(): bool
    {
        $installer = new \TradeCredit\Install\Installer();
        $installer->uninstall();

        Configuration::deleteByName(self::CONFIG_DEFAULT_CREDIT);

        return parent::uninstall();
    }

    /**
     * Module configuration page
     */
    public function getContent(): string
    {
        $output = '';

        if (Tools::isSubmit('submit' . $this->name)) {
            $defaultCredit = (string)Tools::getValue(self::CONFIG_DEFAULT_CREDIT);

            if (!Validate::isFloat($defaultCredit) || (float)$defaultCredit < 0) {
                $output .= $this->displayError(
                    $this->trans('Nieprawidłowa kwota domyślnego kredytu.', [], 'Modules.Tradecredit.Admin')
                );
            } else {
                Configuration::updateValue(self::CONFIG_DEFAULT_CREDIT, $defaultCredit);
                $output .= $this->displayConfirmation(
                    $this->trans('Ustawienia zostały zapisane.', [], 'Modules.Tradecredit.Admin')
                );
            }
        }

        return $output . $this->displayForm();
    }

    /**
     * Build the configuration form
     */
    protected function displayForm(): string
    {
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Ustawienia kredytu kupieckiego', [], 'Modules.Tradecredit.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Domyślna kwota kredytu (PLN)', [], 'Modules.Tradecredit.Admin'),
                        'name' => self::CONFIG_DEFAULT_CREDIT,
                        'class' => 'fixed-width-lg',
                        'desc' => $this->trans(
                            'Domyślna kwota kredytu kupieckiego przydzielana nowym klientom podczas rejestracji.',
                            [],
                            'Modules.Tradecredit.Admin'
                        ),
                        'required' => true,
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Zapisz', [], 'Admin.Actions'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
        $helper->submit_action = 'submit' . $this->name;

        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int)Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');

        $helper->fields_value[self::CONFIG_DEFAULT_CREDIT] = Configuration::get(self::CONFIG_DEFAULT_CREDIT);

        return $helper->generateForm([$form]);
    }

    // =========================================================================
    // FRONT OFFICE HOOKS
    // =========================================================================

    /**
     * Hook: ActionCustomerAccountAdd — add credit field to customer created only on frontend
     */
    public function hookActionCustomerAccountAdd(array $params): void
    {
        $this->saveCustomerCredit($params);
    }

    /**
     * Hook: paymentOptions — display trade credit as payment option on checkout
     */
    public function hookPaymentOptions(array $params): array
    {
        if (!$this->active) {
            return [];
        }

        /** @var Cart $cart */
        $cart = $params['cart'];
        $customer = new Customer((int)$cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            return [];
        }

        $service = $this->getCreditService();
        $availableCredit = $service->getAvailableCredit((int)$customer->id);
        $cartTotal = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $this->context->smarty->assign([
            'available_credit' => number_format($availableCredit, 2, ',', ' '),
            'cart_total' => number_format($cartTotal, 2, ',', ' '),
            'has_enough_credit' => $availableCredit >= $cartTotal,
            'currency_sign' => 'PLN',
        ]);

        $paymentOption = new PaymentOption();
        $paymentOption->setModuleName($this->name)
            ->setCallToActionText($this->trans('Kredyt kupiecki', [], 'Modules.Tradecredit.Shop'))
            ->setAdditionalInformation(
                $this->context->smarty->fetch('module:tradecredit/views/templates/hook/payment_info.tpl')
            );

        // Only set action (allow submission) if customer has enough credit
        if ($availableCredit >= $cartTotal) {
            $paymentOption->setAction(
                $this->context->link->getModuleLink($this->name, 'validation', [], true)
            );
        }

        return [$paymentOption];
    }

    /**
     * Hook: paymentReturn — display confirmation after order
     */
    public function hookPaymentReturn(array $params): string
    {
        if (!$this->active) {
            return '';
        }

        $order = $params['order'] ?? null;
        if (!$order) {
            return '';
        }

        $service = $this->getCreditService();
        $availableCredit = $service->getAvailableCredit((int)$order->id_customer);

        $this->context->smarty->assign([
            'shop_name' => $this->context->shop->name,
            'available_credit' => number_format($availableCredit, 2, ',', ' '),
            'currency_sign' => 'PLN',
        ]);

        return $this->context->smarty->fetch('module:tradecredit/views/templates/hook/payment_return.tpl');
    }

    // =========================================================================
    // ORDER HOOKS
    // =========================================================================

    /**
     * Hook: actionValidateOrder — deduct credit when order is placed
     */
    public function hookActionValidateOrder(array $params): void
    {
        /** @var Order $order */
        $order = $params['order'];

        if ($order->module !== $this->name) {
            return;
        }

        $service = $this->getCreditService();
        $service->deductCredit((int)$order->id_customer, (float)$order->total_paid_tax_incl);
    }

    /**
     * Hook: actionOrderStatusPostUpdate — restore credit on cancellation
     */
    public function hookActionOrderStatusPostUpdate(array $params): void
    {
        $newStatus = (int)$params['newOrderStatus']->id;
        $orderId = (int)$params['id_order'];

        // PS_OS_CANCELED
        $cancelledStatus = (int)Configuration::get('PS_OS_CANCELED');
        if ($newStatus !== $cancelledStatus) {
            return;
        }

        $order = new Order($orderId);
        if ($order->module !== $this->name) {
            return;
        }

        $service = $this->getCreditService();
        $service->restoreCredit((int)$order->id_customer, (float)$order->total_paid_tax_incl);
    }

    // =========================================================================
    // ADMIN HOOKS — Customer form
    // =========================================================================

    /**
     * Hook: actionCustomerFormBuilderModifier — add credit field to customer form
     */
    public function hookActionCustomerFormBuilderModifier(array $params): void
    {
        /** @var \Symfony\Component\Form\FormBuilderInterface $formBuilder */
        $formBuilder = $params['form_builder'];
        $customerId = isset($params['id']) ? (int)$params['id'] : 0;

        $service = $this->getCreditService();
        $currentCredit = $customerId > 0
            ? $service->getAvailableCredit($customerId)
            : (float)Configuration::get(self::CONFIG_DEFAULT_CREDIT);

        $formBuilder->add('trade_credit_amount', \Symfony\Component\Form\Extension\Core\Type\NumberType::class, [
            'label' => $this->trans('Kredyt kupiecki (PLN)', [], 'Modules.Tradecredit.Admin'),
            'required' => false,
            'data' => $currentCredit,
            'attr' => [
                'step' => '0.01',
            ],
            'help' => $this->trans(
                'Dostępna kwota kredytu kupieckiego dla tego klienta.',
                [],
                'Modules.Tradecredit.Admin'
            ),
        ]);

        // Provide data for the form
        $params['data']['trade_credit_amount'] = $currentCredit;
        $formBuilder->setData($params['data']);
    }

    /**
     * Hook: actionAfterCreateCustomerFormHandler — save credit on customer creation in backoffice
     */
    public function hookActionAfterCreateCustomerFormHandler(array $params): void
    {
        $this->saveCustomerCredit($params);
    }

    /**
     * Hook: actionAfterUpdateCustomerFormHandler — save credit on customer update in backoffice
     */
    public function hookActionAfterUpdateCustomerFormHandler(array $params): void
    {
        $this->saveCustomerCredit($params);
    }


    /**
     * Save credit amount for customer
     */
    private function saveCustomerCredit(array $params): void
    {
        $customerId = (int)($params['id'] ?? $params['newCustomer']->id);
        $formData = $params['form_data'] ?? [];
        $creditAmount = isset($formData['trade_credit_amount'])
            ? (float)$formData['trade_credit_amount']
            : (float)Configuration::get(self::CONFIG_DEFAULT_CREDIT);

        $service = $this->getCreditService();

        $service->setCredit($customerId, $creditAmount);
    }

    // =========================================================================
    // SERVICE
    // =========================================================================

    /**
     * Get TradeCreditService instance
     */
    private function getCreditService(): \TradeCredit\Service\TradeCreditService
    {
        return new \TradeCredit\Service\TradeCreditService();
    }
}
