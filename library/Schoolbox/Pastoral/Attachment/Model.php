<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Attachment;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Pastoral;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\Resource\Storage\IStorage;

class Model implements IStorage, ISoftDeletable
{

    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
    use StorableTrait;

    /** @var Pastoral\Model $pastoral */
    private $pastoral;


    /**
     * @param Pastoral\Model $pastoral
     * @return self
     */
    public function setPastoral(Pastoral\Model $pastoral)
    {
        $this->pastoral = $pastoral;
        return $this;
    }


    /**
     * @return Pastoral\Model
     */
    public function getPastoral()
    {
        return $this->pastoral;
    }
}
