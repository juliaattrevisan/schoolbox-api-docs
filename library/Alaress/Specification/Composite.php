<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Specification;

abstract class Composite extends Base
{

    /** @var ISpecification[] */
    private $components;


    /**
     * @param ISpecification[] $components
     */
    public function __construct(array $components)
    {
        $this->components = $components;
    }


    /**
     * @return ISpecification[]
     */
    public function getComponents(): array
    {
        return $this->components;
    }
}
