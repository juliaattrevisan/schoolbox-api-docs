<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Field\Availability;

use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model
{
    use ModelTrait\Id;


    /**
     * @var \Schoolbox\Admin\Profile\Editor\Field\Model|null
     */
    private ?\Schoolbox\Admin\Profile\Editor\Field\Model $field = null;


    /**
     * @var \Schoolbox\Campus|null
     */
    private ?\Schoolbox\Campus $campus = null;


    /**
     * @return \Schoolbox\Admin\Profile\Editor\Field\Model|null
     */
    public function getField(): ?\Schoolbox\Admin\Profile\Editor\Field\Model
    {
        return $this->field;
    }


    /**
     * @param \Schoolbox\Admin\Profile\Editor\Field\Model|null $field
     * @return $this
     */
    public function setField(?\Schoolbox\Admin\Profile\Editor\Field\Model $field): self
    {
        $this->field = $field;
        return $this;
    }


    /**
     * @return \Schoolbox\Campus|null
     */
    public function getCampus(): ?\Schoolbox\Campus
    {
        return $this->campus;
    }


    /**
     * @param \Schoolbox\Campus|null $campus
     * @return $this
     */
    public function setCampus(?\Schoolbox\Campus $campus): self
    {
        $this->campus = $campus;
        return $this;
    }
}
