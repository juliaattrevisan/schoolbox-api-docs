<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

use Alaress_Date_Format;
use Alaress\Json;

/**
 * Wrapper for thread models, so that they may have links output as part of
 * their JSON representation.
 *
 * Intended to be extended: subclass this class and implement `getJsonApiLinks`.
 */
abstract class ModelWrapper implements Json\Serializable, Json\Linkable
{
    use Json\SerializeTrait;


    /**
     * @var Model
     */
    protected $model;


    /**
     * Constructor
     *
     * @param Model $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }


    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->model->getId();
    }


    /**
     * @return bool|null
     */
    public function getOpen(): ?bool
    {
        return $this->model->getOpen();
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
     * @return array
     */
    public function getDefaultJsonMap(): array
    {
        return [
            'id',
            'open',
            'createdAt',
            'updatedAt',
        ];
    }
}
