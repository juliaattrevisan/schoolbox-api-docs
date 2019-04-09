<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread\ModelWrapper;

use Alaress_Date_Format;
use Alaress\Json;
use Schoolbox\Discussion\Thread\Context\IContext;
use Schoolbox\Discussion\Thread\Model;

/**
 * Wrapper for thread models, so that they may have links output as part of
 * their JSON representation.
 *
 * Intended to be extended: subclass this class and implement `getJsonApiLinks`.
 */
abstract class BaseWrapper implements Json\Serializable, Json\Linkable
{
    use Json\SerializeTrait;


    /**
     * @var Model
     */
    protected $model;


    /**
     * @var IContext
     */
    protected $context;


    /**
     * Constructor
     *
     * @param Model    $model
     * @param IContext $context
     */
    public function __construct(Model $model, IContext $context)
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
