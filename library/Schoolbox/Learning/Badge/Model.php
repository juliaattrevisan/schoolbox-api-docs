<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Badge;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Resource\Storage;
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
     * @var string
     */
    private string $title;


    /**
     * @var string
     */
    private string $body;


    /**
     * Optional image associated with this Badge
     *
     * @var Storage\Model
     */
    private Storage\Model $image;


    /**
     * The Badge's Category
     *
     * @var Category\Model
     */
    private Category\Model $category;


    /**
     * The user who created this Badge
     *
     * @var User
     */
    private User $creator;


    /**
     * The user who updated this Badge
     *
     * @var User
     */
    private User $updator;


    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }


    /**
     * @param string $title
     * @return Model
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }


    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }


    /**
     * @param string $body
     * @return Model
     */
    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }


    /**
     * @return Storage\Model
     */
    public function getImage(): Storage\Model
    {
        return $this->image;
    }


    /**
     * @param Storage\Model $image
     * @return $this
     */
    public function setImage(Storage\Model $image): self
    {
        $this->image = $image;
        return $this;
    }


    /**
     * @return Category\Model
     */
    public function getCategory(): Category\Model
    {
        return $this->category;
    }


    /**
     * @param Category\Model $category
     * @return $this
     */
    public function setCategory(Category\Model $category): self
    {
        $this->category = $category;
        return $this;
    }


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
     * @return User
     */
    public function getUpdator(): User
    {
        return $this->updator;
    }


    /**
     * @param User $updator
     * @return $this
     */
    public function setUpdator(User $updator): self
    {
        $this->updator = $updator;
        return $this;
    }
}
