<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Tab\Availability\Campus;

use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model
{
    use ModelTrait\Id;


    /**
     * @var \Schoolbox\Admin\Profile\Editor\Tab\Model|null
     */
    private ?\Schoolbox\Admin\Profile\Editor\Tab\Model $tab = null;


    /**
     * @var \Schoolbox\Campus|null
     */
    private ?\Schoolbox\Campus $campus = null;


    /**
     * @return \Schoolbox\Admin\Profile\Editor\Tab\Model|null
     */
    public function getTab(): ?\Schoolbox\Admin\Profile\Editor\Tab\Model
    {
        return $this->tab;
    }


    /**
     * @param \Schoolbox\Admin\Profile\Editor\Tab\Model|null $tab
     * @return $this
     */
    public function setTab(?\Schoolbox\Admin\Profile\Editor\Tab\Model $tab): self
    {
        $this->tab = $tab;
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
