<?php

declare(strict_types=1);

namespace TradeCredit\Service;

use Configuration;
use Db;

class TradeCreditService
{
    /**
     * Get available credit for a customer.
     * If no record exists, creates one with default credit amount.
     */
    public function getAvailableCredit(int $customerId): float
    {
        $this->ensureCreditRecord($customerId);

        $result = Db::getInstance()->getValue(
            'SELECT `available_credit` FROM `' . _DB_PREFIX_ . 'trade_credit` 
             WHERE `id_customer` = ' . (int) $customerId
        );

        return $result !== false ? (float) $result : 0.0;
    }

    /**
     * Set credit amount for a customer (admin edit).
     */
    public function setCredit(int $customerId, float $amount): bool
    {
        $this->ensureCreditRecord($customerId);

        return Db::getInstance()->update(
            'trade_credit',
            [
                'available_credit' => (float) $amount,
                'date_upd' => date('Y-m-d H:i:s'),
            ],
            'id_customer = ' . (int) $customerId
        );
    }

    /**
     * Deduct credit after a successful order.
     */
    public function deductCredit(int $customerId, float $amount): bool
    {
        $currentCredit = $this->getAvailableCredit($customerId);
        $newCredit = max(0, $currentCredit - $amount);

        return $this->setCredit($customerId, $newCredit);
    }

    /**
     * Restore credit when order is cancelled.
     */
    public function restoreCredit(int $customerId, float $amount): bool
    {
        $currentCredit = $this->getAvailableCredit($customerId);
        $newCredit = $currentCredit + $amount;

        return $this->setCredit($customerId, $newCredit);
    }

    /**
     * Check if customer has enough credit for given amount.
     */
    public function hasEnoughCredit(int $customerId, float $amount): bool
    {
        return $this->getAvailableCredit($customerId) >= $amount;
    }

    /**
     * Ensure a credit record exists for the customer.
     * Creates one with default amount if it doesn't exist.
     */
    private function ensureCreditRecord(int $customerId): void
    {
        $exists = Db::getInstance()->getValue(
            'SELECT `id_trade_credit` FROM `' . _DB_PREFIX_ . 'trade_credit` 
             WHERE `id_customer` = ' . (int) $customerId
        );

        if (!$exists) {
            $defaultCredit = (float) Configuration::get('TRADE_CREDIT_DEFAULT_AMOUNT');
            if ($defaultCredit <= 0) {
                $defaultCredit = 50000.00;
            }

            $now = date('Y-m-d H:i:s');

            Db::getInstance()->insert('trade_credit', [
                'id_customer' => (int) $customerId,
                'available_credit' => (float) $defaultCredit,
                'date_add' => $now,
                'date_upd' => $now,
            ]);
        }
    }
}
