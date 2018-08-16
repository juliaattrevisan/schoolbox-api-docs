<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use Schoolbox\GenericCollection;

/**
 * A level within a rubric grid.
 *
 * Represented on the rubric grid as a column.
 */
class Level
{
    /**
     * @var GenericCollection|Descriptor[]
     */
    private $descriptors;


    /**
     * @var string|null
     */
    private $name;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->descriptors = new GenericCollection(Descriptor::class);
    }


    /**
     * @return GenericCollection|Descriptor[]
     */
    public function getDescriptors(): GenericCollection
    {
        return $this->descriptors;
    }


    /**
     * @param GenericCollection|Descriptor[] $descriptors
     * @return static
     */
    public function setDescriptors(GenericCollection $descriptors): self
    {
        $this->descriptors = $descriptors;
        return $this;
    }


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
