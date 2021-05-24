<?php

/**
 * @author      James Leckie <jleckie@schoolbox.com.au>
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Config\Type;

use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\HasSequence;
use Schoolbox\Sequential;

class Model implements Sequential
{
    use Id;
    use HasSequence;

    /**
     * The type name
     *
     * @var string
     */
    protected $type;

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return self
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }
}
