<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use Schoolbox\Mapper\Traits\Model\Id;

/**
 * A level within a rubric grid.
 *
 * Represented on the rubric grid as a column.
 */
class Level
{
    use Id;


    /**
     * @var string|null
     */
    private $name;


    /**
     * @return null|string
     */
    public function getName(): ?string
    {
        return $this->name;
    }


    /**
     * @param null|string $name
     * @return static
     */
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }
}
