<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Comment;

use Alaress\Json;
use Schoolbox\Discussion\Thread;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\User;

/**
 * Model for comments in discussion threads.
 */
class Model implements ISoftDeletable, Json\Serializable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
    use Json\SerializeTrait;


    /**
     * @var Thread\Model
     */
    private $thread;


    /**
     * @var User
     */
    private $author;


    /**
     * @var string
     */
    private $content;


    /**
     * @return Thread\Model|null
     */
    public function getThread(): ?Thread\Model
    {
        return $this->thread;
    }


    /**
     * @param Thread\Model $thread
     * @return static
     */
    public function setThread(Thread\Model $thread): self
    {
        $this->thread = $thread;
        return $this;
    }


    /**
     * @return User|null
     */
    public function getAuthor(): ?User
    {
        return $this->author;
    }


    /**
     * @param User $author
     * @return static
     */
    public function setAuthor(User $author): self
    {
        $this->author = $author;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }


    /**
     * @param string $content
     * @return static
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }


    /**
     * @return array
     */
    public function getDefaultJsonMap(): array
    {
        // note: datetimes should be RFC3339
        $map = [
            'id',
            'content',
            'createdAt',
            'updatedAt',
        ];
        if ($this->author) {
            $map['author'] = $this->author->getDefaultJsonMap();
        }
        return $map;
    }
}
