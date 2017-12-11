<?php
/**
 * Trait for model classes - adds a tour field.
 *
 * @tour    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

use Schoolbox\Tour\Model as TourModel;

trait Tour
{
    /**
     * @var TourModel|null
     */
    protected $tour;

    /**
     * @return TourModel|null
     */
    public function getTour(): ?TourModel
    {
        return $this->tour;
    }

    /**
     * @param TourModel|null $tour
     *
     * @return self|$this
     */
    public function setTour(?TourModel $tour): self
    {
        $this->tour = $tour;
        return $this;
    }
}
