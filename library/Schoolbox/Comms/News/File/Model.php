<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\File;

use Alaress_Date_Format;
use Schoolbox\Comms\News;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\User;

/**
 * File attached to an Article.
 */
class Model implements IFile, ISoftDeletable
{
    use StorableTrait;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;


    /**
     * @var News\Model|null
     */
    private ?News\Model $article = null;


    /**
     * @return News\Model|null
     */
    public function getArticle(): ?News\Model
    {
        return $this->article;
    }


    /**
     * @param News\Model|null $article
     * @return $this
     */
    public function setArticle(?News\Model $article): self
    {
        $this->article = $article;
        return $this;
    }


    /**
     * @return User
     */
    public function getOwner(): User
    {
        return $this->article->getAuthor();
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedAt(): Alaress_Date_Format
    {
        return $this->storage->getCreatedAt();
    }


    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return ($this->getDeletedAt() instanceof Alaress_Date_Format && !$this->getDeletedAt()->isNull());
    }
}
