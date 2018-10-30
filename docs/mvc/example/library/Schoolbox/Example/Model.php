<?php
/**
 * Created by PhpStorm.
 * User: matt.schembri
 * Date: 30/04/18
 * Time: 1:28 PM
 */

namespace Schoolbox\Example;


use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;


class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    /**
     * @var \Schoolbox\User
     */
    private $user;


    /**
     * @return \Schoolbox\User
     */
    public function getUser(): \Schoolbox\User
    {
        return $this->user;
    }


    /**
     * @param \Schoolbox\User $user
     * @return $this
     *
     */
    public function setUser(\Schoolbox\User $user): void
    {
        $this->user = $user;
        return $this;
    }

}