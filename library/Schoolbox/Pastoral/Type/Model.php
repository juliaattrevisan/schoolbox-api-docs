<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Type;

use Alaress\Color\Color;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Pastoral\DocumentClassification\Model as DocumentClassification;
use Schoolbox\Pastoral;
use DomainException;
use Schoolbox\Sequential;

class Model implements ISoftDeletable, Sequential
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\UpdatedBy;
    use ModelTrait\HasSequence;

    /** @var string */
    protected $name;


    /**
     * @var string|null
     */
    private ?string $template = null;


    /**
     * This type's color.
     */
    private ?Color $color;


    /**
     * This type's icon.
     */
    private ?string $icon;


    /** @var DocumentClassification */
    protected $documentClassification;

    /** @var string */
    protected $externalCode;

    /** @var GenericCollection|Pastoral\SubType\Model[] */
    private $subTypes;

    /**
     * Visibility locked
     * Defaults to false, when created from the admin page.
     */
    private bool $visibilityLocked = false;


    public function __construct()
    {
        $this->subTypes = new GenericCollection(Pastoral\SubType\Model::class);
    }


    /**
     * @param DocumentClassification $documentClassification
     *
     * @return self
     */
    public function setDocumentClassification($documentClassification)
    {
        $this->documentClassification = $documentClassification;

        return $this;
    }

    /**
     * @return DocumentClassification
     */
    public function getDocumentClassification()
    {
        return $this->documentClassification;
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $externalCode
     *
     * @return self
     */
    public function setExternalCode($externalCode)
    {
        $this->externalCode = $externalCode;

        return $this;
    }

    /**
     * @return string
     */
    public function getExternalCode()
    {
        return $this->externalCode;
    }


    /**
     * @return GenericCollection|Pastoral\SubType\Model[]
     */
    public function getSubTypes(): GenericCollection
    {
        return $this->subTypes;
    }


    /**
     * @param Collection|Pastoral\SubType\Model[] $subTypes
     * @return $this
     * @throws DomainException
     */
    public function setSubTypes(Collection $subTypes): self
    {
        $this->subTypes->replaceWith($subTypes);
        return $this;
    }


    /**
     * @return string|null
     */
    public function getTemplate(): ?string
    {
        return $this->template;
    }


    /**
     * @param string|null $template
     * @return Model
     */
    public function setTemplate(?string $template): self
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @return Color|null
     */
    public function getColor(): ?Color
    {
        return $this->color;
    }

    /**
     * @param Color|null $color
     */
    public function setColor(?Color $color): void
    {
        $this->color = $color;
    }

    /**
     * @return string|null
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * @param string|null $icon
     */
    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
    }

    /**
     * @return bool
     */
    public function getVisibilityLocked(): bool
    {
        return $this->visibilityLocked;
    }

    /**
     * @param bool $visibilityLocked
     * @return static
     */
    public function setVisibilityLocked(bool $visibilityLocked): self
    {
        $this->visibilityLocked = $visibilityLocked;
        return $this;
    }
}
