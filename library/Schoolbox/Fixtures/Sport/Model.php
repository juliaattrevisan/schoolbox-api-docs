<?php
/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Sport;

use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Fixtures\Position\Model as Position;

class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;

    /** @var string */
    protected $name;

    /** @var string */
    protected $url;

    /** @var Collection|Position[] */
    protected $positions;

    /**
     * @param string $name
     * @return self
     */
    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param string $url
     * @return self
     */
    public function setUrl($url) {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl() {
        return $this->url;
    }


    /**
     * @return Collection|Position[]
     */
    public function getPositions()
    {
        return $this->positions;
    }


    /**
     * @param Collection|Position[] $positions
     * @return static
     */
    public function setPositions($positions)
    {
        $this->positions = $positions;
        return $this;
    }

}
