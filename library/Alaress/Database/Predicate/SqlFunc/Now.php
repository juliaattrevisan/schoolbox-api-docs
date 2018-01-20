<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Alaress\Database\Predicate\SqlFunc;

use Alaress\Database\Predicate\AbstractSqlFunc;
use Alaress\Database\Quoter\IQuoter;

/**
 * SQL NOW() function
 */
class Now extends AbstractSqlFunc
{

    /**
     * @inheritdoc
     */
    public function getParam(): string
    {
        return 'now';
    }


    /**
     * @inheritdoc
     */
    public function getEscapedOperand(IQuoter $quoter, string $defaultTable): string
    {
        return 'NOW()';
    }
}
