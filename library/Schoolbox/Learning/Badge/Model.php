<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Badge;

use Schoolbox\GenericCollection;
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
     * @var GenericCollection
     */
    private GenericCollection $curriculumNodes;


    /**
     * @var User
     */
    private User $createdBy;


    /**
     * @var User
     */
    private User $updatedBy;


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
     * @return GenericCollection
     */
    public function getCurriculumNodes(): GenericCollection
    {
        return $this->curriculumNodes;
    }


    /**
     * @param GenericCollection $curriculumNodes
     * @return Model
     */
    public function setCurriculumNodes(GenericCollection $curriculumNodes): self
    {
        $this->curriculumNodes = $curriculumNodes;
        return $this;
    }


    /**
     * @return User
     */
    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }


    /**
     * @param User $createdBy
     * @return $this
     *
     */
    public function setCreatedBy(User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }


    /**
     * @return User
     */
    public function getUpdatedBy(): User
    {
        return $this->updatedBy;
    }


    /**
     * @param User $updatedBy
     * @return $this
     */
    public function setUpdatedBy(User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }
}
