<?php

declare(strict_types=1);

namespace BeycanPress\CryptoPay\LearnDash\Models;

use BeycanPress\CryptoPay\Models\AbstractTransaction;

class TransactionsPro extends AbstractTransaction
{
    public string $addon = 'learndash';

    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct('learndash_transaction');
    }
}
