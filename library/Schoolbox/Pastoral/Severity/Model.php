<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Severity;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\HasSequence;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Sequential;

class Model implements ISoftDeletable, Sequential
{
    use Id;
    use HasSequence;
    use DeletedAtDate;

    /** @var string */
    protected $name;

    /** @var bool $dashboard */
    protected $dashboard;

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param bool $dashboard
     *
     * @return self
     */
    public function setDashboard($dashboard)
    {
        $this->dashboard = $dashboard;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDashboard()
    {
        return $this->dashboard;
    }
}
