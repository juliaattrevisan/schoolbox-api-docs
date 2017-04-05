<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Database\Quoter;

/**
 * Class Base
 */
abstract class Base implements IQuoter
{
    /**
     * @inheritdoc
     */
    public function escapeForLike($string)
    {
        return strtr(
            $string,
            array(
                '_' => '\\_',
                '%' => '\\%',
            )
        );
    }
}
