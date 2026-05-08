<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel;

use Schoolbox\Learning\Assessment\DueWork\Decorator\CourseManagedDecorator;
use Schoolbox\Learning\Assessment\DueWork\Decorator\Decorator;
use Schoolbox\Learning\Assessment\Factory;

trait RubricTrait
{
    /**
     * A json payload that represents fields for a rubric_instance but also the grid contents itself. Typically this
     * comes from the frontend modify screen's ui as part of a mega payload which gets disseminated into rubric_instance
     *
     * @var array|null
     */
    private ?array $rubricData = null;


    /**
     * @return array|null $rubricData
     */
    public function getRubricData(): ?array
    {
        return $this->rubricData;
    }


    /**
     * A json-encoded string corresponding to rubric title, weighting and levels (the grid contents)
     *
     * @param array|null $rubricData
     * @return Factory|RubricTrait|CourseManagedDecorator|Decorator
     */
    public function setRubricData(?array $rubricData = null): self
    {
        $this->rubricData = $rubricData;
        return $this;
    }
}
