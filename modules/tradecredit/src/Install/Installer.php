<?php

declare(strict_types=1);

namespace TradeCredit\Install;

use Db;

class Installer
{
    /**
     * Install: create database table
     */
    public function install(): bool
    {
        return Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'trade_credit` (
                `id_trade_credit` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_customer` INT(10) UNSIGNED NOT NULL,
                `available_credit` DECIMAL(20, 6) NOT NULL DEFAULT 50000.000000,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_trade_credit`),
                UNIQUE KEY `idx_customer` (`id_customer`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    /**
     * Uninstall: remove database table
     */
    public function uninstall(): bool
    {
        return Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'trade_credit`'
        );
    }
}
