<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Comment\ModelDecorator;

use Alaress_Date_Format;
use Alaress\Json;
use Schoolbox\Discussion\Comment\Model;
use Schoolbox\Discussion\Thread\Context\IThreadLocation;
use Schoolbox\User;

/**
 * Decorator for comment models, so that they may have links output as part of
 * their JSON representation.
 *
 * Intended to be extended: subclass this class and implement `getJsonApiLinks`.
 */
abstract class BaseDecorator implements Json\Serializable, Json\Linkable
{
    use Json\SerializeTrait;


    /**
     * @var Model
     */
    protected $model;


    /**
     * @var IThreadLocation
     */
    protected $context;


    /**
     * Constructor
     *
     * @param Model    $model
     * @param IThreadLocation $context
     */
    public function __construct(Model $model, IThreadLocation $context)
    {
        $this->model   = $model;
        $this->context = $context;
    }


    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->model->getId();
    }


    /**
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->model->getContent();
    }


    /**
     * @return Alaress_Date_Format|null
     */
    public function getCreatedAt(): ?Alaress_Date_Format
    {
        return $this->model->getCreatedAt();
    }


    /**
     * @return Alaress_Date_Format|null
     */
    public function getUpdatedAt(): ?Alaress_Date_Format
    {
        return $this->model->getUpdatedAt();
    }


    /**
     * @return User|null
     */
    public function getAuthor(): ?User
    {
        return $this->model->getAuthor();
    }


    /**
     * @return array
     */
    public function getDefaultJsonMap(): array
    {
        $map = [
            'id',
            'content',
            'createdAt',
            'updatedAt',
        ];
        $author = $this->getAuthor();
        if ($author) {
            $map['author'] = $author->getDefaultJsonMap();
        }
        return $map;
    }
}
