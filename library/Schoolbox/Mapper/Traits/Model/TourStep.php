<?php
/**
 * Trait for model classes - adds a tourStep field.
 *
 * @tourStep    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

use Schoolbox\Tour\Step\Model as TourStepModel;

trait TourStep
{
    /**
     * @var TourStepModel|null
     */
    protected $tourStep;

    /**
     * @return TourStepModel|null
     */
    public function getTourStep(): ?TourStepModel
    {
        return $this->tourStep;
    }

    /**
     * @param TourStepModel|null $tourStep
     *
     * @return self|$this
     */
    public function setTourStep(?TourStepModel $tourStep): self
    {
        $this->tourStep = $tourStep;
        return $this;
    }
}
