<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Notification\Parameter;

use Alaress\Async\Notification\Parameter\Param;

class SimpleParam extends Param
{

    /**
     * Display the parameter without any decoration
     *
     * @param string $value
     */
    public function __construct(string $value)
    {
        parent::__construct($value, null, Param::STACKING_REPLACE_NO_DECORATION);
    }

}
