<?php

declare(strict_types=1);

namespace TradeCredit\Tests\Service;

use PHPUnit\Framework\TestCase;
use TradeCredit\Service\TradeCreditService;

/**
 * Unit tests for TradeCreditService
 *
 * To run within a PrestaShop 9 installation:
 *   cd /path/to/prestashop/modules/tradecredit
 *   php vendor/bin/phpunit tests/
 *
 */
class TradeCreditServiceTest extends TestCase
{
    private TradeCreditService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TradeCreditService();
    }

    public function testGetAvailableCreditReturnsDefaultForNewCustomer(): void
    {
        $customerId = $this->createTestCustomer();

        try {
            $credit = $this->service->getAvailableCredit($customerId);
            $defaultCredit = (float)\Configuration::get('TRADE_CREDIT_DEFAULT_AMOUNT');

            $this->assertEquals($defaultCredit, $credit, 'New customer should get default credit');
        } finally {
            $this->cleanupTestCustomer($customerId);
        }
    }

    public function testSetCreditUpdatesAmount(): void
    {
        $customerId = $this->createTestCustomer();

        try {
            $this->service->setCredit($customerId, 30000.00);
            $credit = $this->service->getAvailableCredit($customerId);

            $this->assertEquals(30000.00, $credit);
        } finally {
            $this->cleanupTestCustomer($customerId);
        }
    }

    public function testDeductCreditReducesBalance(): void
    {
        $customerId = $this->createTestCustomer();

        try {
            $this->service->setCredit($customerId, 50000.00);
            $this->service->deductCredit($customerId, 15000.00);

            $this->assertEquals(35000.00, $this->service->getAvailableCredit($customerId));
        } finally {
            $this->cleanupTestCustomer($customerId);
        }
    }

    public function testDeductCreditNeverGoesBelowZero(): void
    {
        $customerId = $this->createTestCustomer();

        try {
            $this->service->setCredit($customerId, 100.00);
            $this->service->deductCredit($customerId, 500.00);

            $this->assertEquals(0.0, $this->service->getAvailableCredit($customerId));
        } finally {
            $this->cleanupTestCustomer($customerId);
        }
    }

    public function testRestoreCreditIncreasesBalance(): void
    {
        $customerId = $this->createTestCustomer();

        try {
            $this->service->setCredit($customerId, 35000.00);
            $this->service->restoreCredit($customerId, 15000.00);

            $this->assertEquals(50000.00, $this->service->getAvailableCredit($customerId));
        } finally {
            $this->cleanupTestCustomer($customerId);
        }
    }

    public function testHasEnoughCreditReturnsTrueWhenSufficient(): void
    {
        $customerId = $this->createTestCustomer();

        try {
            $this->service->setCredit($customerId, 50000.00);
            $this->assertTrue($this->service->hasEnoughCredit($customerId, 49999.99));
        } finally {
            $this->cleanupTestCustomer($customerId);
        }
    }

    public function testHasEnoughCreditReturnsFalseWhenInsufficient(): void
    {
        $customerId = $this->createTestCustomer();

        try {
            $this->service->setCredit($customerId, 100.00);
            $this->assertFalse($this->service->hasEnoughCredit($customerId, 200.00));
        } finally {
            $this->cleanupTestCustomer($customerId);
        }
    }

    public function testHasEnoughCreditReturnsTrueWhenExactAmount(): void
    {
        $customerId = $this->createTestCustomer();

        try {
            $this->service->setCredit($customerId, 500.00);
            $this->assertTrue($this->service->hasEnoughCredit($customerId, 500.00));
        } finally {
            $this->cleanupTestCustomer($customerId);
        }
    }

    public function testMultipleDeductionsAccumulate(): void
    {
        $customerId = $this->createTestCustomer();

        try {
            $this->service->setCredit($customerId, 50000.00);
            $this->service->deductCredit($customerId, 10000.00);
            $this->service->deductCredit($customerId, 15000.00);
            $this->service->deductCredit($customerId, 5000.00);

            $this->assertEquals(20000.00, $this->service->getAvailableCredit($customerId));
        } finally {
            $this->cleanupTestCustomer($customerId);
        }
    }

    public function testDeductThenRestoreReturnsToPreviousBalance(): void
    {
        $customerId = $this->createTestCustomer();

        try {
            $this->service->setCredit($customerId, 50000.00);
            $this->service->deductCredit($customerId, 12345.67);
            $this->service->restoreCredit($customerId, 12345.67);

            $this->assertEquals(50000.00, $this->service->getAvailableCredit($customerId));
        } finally {
            $this->cleanupTestCustomer($customerId);
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createTestCustomer(): int
    {
        $email = 'test_tc_' . uniqid() . '@example.com';
        \Db::getInstance()->insert('customer', [
            'firstname' => 'Test',
            'lastname' => 'TradeCredit',
            'email' => $email,
            'passwd' => md5('test'),
            'id_default_group' => 1,
            'id_shop' => 1,
            'id_shop_group' => 1,
            'id_lang' => 1,
            'active' => 1,
            'date_add' => date('Y-m-d H:i:s'),
            'date_upd' => date('Y-m-d H:i:s'),
        ]);

        return (int)\Db::getInstance()->Insert_ID();
    }

    private function cleanupTestCustomer(int $customerId): void
    {
        \Db::getInstance()->delete('trade_credit', 'id_customer = ' . (int)$customerId);
        \Db::getInstance()->delete('customer', 'id_customer = ' . (int)$customerId);
    }
}
