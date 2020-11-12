<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Category;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

/**
 * Class Model
 *
 * @package Schoolbox\Learning\Badge\Category
 */
class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;


    /**
     * The user who created this category of Badge
     *
     * @var User
     */
    private User $creator;


    /**
     * @var string
     */
    private string $name;


    /**
     * @return User
     */
    public function getCreator(): User
    {
        return $this->creator;
    }


    /**
     * @param User $creator
     * @return $this
     *
     */
    public function setCreator(User $creator): self
    {
        $this->creator = $creator;
        return $this;
    }


    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}
