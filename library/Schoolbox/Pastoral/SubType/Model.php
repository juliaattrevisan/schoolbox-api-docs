<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\SubType;

use Schoolbox\Pastoral\Type\TypeAbstract;
use Schoolbox\Pastoral\Type\Model as PastoralType;

class Model extends TypeAbstract
{
    /**
     * @var PastoralType
     */
    private $pastoralType;

    /**
     * @param PastoralType $pastoralType
     *
     * @return self
     */
    public function setPastoralType($pastoralType): self
    {
        $this->pastoralType = $pastoralType;

        return $this;
    }

    /**
     * @return PastoralType
     */
    public function getPastoralType()
    {
        return $this->pastoralType;
    }
}
