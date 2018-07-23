<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Criteria;

use Schoolbox\Assessment\Rubric\DescriptorList;

/**
 * Object implementation of the usage of a rubric criterion on a rubric grid, as
 * opposed to the criterion as available on a folder.
 */
class CriterionUsage extends DescriptorList
{
    /**
     * @var Criterion
     */
    private $criterion;

    /**
     * Returns the total of the values of descriptors contained herein, irrespective of selection
     *
     * @return float
     */
    public function getTotal(): float
    {
        $total = 0;

        foreach ($this->descriptors as $descriptor) {
            if (is_numeric($descriptor->getTotal())) {
                $total = max($total, $descriptor->getTotal());
            }
        }

        return $total;
    }


    /**
     * Convert yourself into a Twig context array
     *
     * @return array
     */
    public function toContext(): array
    {
        $context = parent::toContext();

        $context['used'] = $this->criterion->isReadOnly();

        return $context;
    }
}
