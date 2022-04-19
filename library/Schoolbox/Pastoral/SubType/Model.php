<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\SubType;

use Alaress\Color\Color;
use JsonSerializable;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Pastoral\Action\Model as Action;
use Schoolbox\Pastoral\Type\Model as PastoralType;
use Schoolbox\Sequential;

class Model implements ISoftDeletable, JsonSerializable, Sequential
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\UpdatedBy;
    use ModelTrait\HasSequence;

    /**
     * @var PastoralType
     */
    private $pastoralType;

    /**
     * @var string|null
     */
    private ?string $name = null;


    /**
     * @var string|null
     */
    private ?string $template = null;

    /**
     * @var string|null
     */
    private ?string $templatePrivate = null;

    /**
     * This type's color.
     */
    private ?Color $color = null;


    /**
     * This type's icon.
     */
    private ?string $icon = null;


    /**
     * Optional. Null if not linked to SIS
     *
     * @var null|string
     */
    private ?string $externalCode = null;


    /**
     * Visibility locked
     * Defaults to false, when created from the admin page.
     */
    private bool $visibilityLocked = false;

    /**
     * Sentiment 'positive', 'neutral', 'negative'
     */
    private string $sentiment = PastoralType::SENTIMENT_NEUTRAL;

    /** @var GenericCollection|Action[] */
    private GenericCollection $actions;

    public function __construct()
    {
        $this->actions = new GenericCollection(Action::class);
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param PastoralType $pastoralType
     *
     * @return self
     */
    public function setPastoralType($pastoralType): self
    {
        $this->pastoralType = $pastoralType;

        return $this;
    }

    /**
     * @return PastoralType
     */
    public function getPastoralType()
    {
        return $this->pastoralType;
    }

    /**
     * @param null|string $externalCode
     *
     * @return self
     */
    public function setExternalCode(?string $externalCode = null): self
    {
        $this->externalCode = $externalCode;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getExternalCode(): ?string
    {
        return $this->externalCode;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'id'   => $this->getId(),
            'name' => $this->getName(),
        ];
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
     * @return string|null
     */
    public function getTemplatePrivate(): ?string
    {
        return $this->templatePrivate;
    }


    /**
     * @param string|null $templatePrivate
     * @return Model
     */
    public function setTemplatePrivate(?string $templatePrivate): self
    {
        $this->templatePrivate = $templatePrivate;
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

    /**
     * @return string
     */
    public function getSentiment(): string
    {
        return $this->sentiment;
    }

    /**
     * @param string $sentiment
     * @return static
     */
    public function setSentiment(string $sentiment): self
    {
        $this->sentiment = $sentiment;
        return $this;
    }

    /**
     * @return GenericCollection|Action[]
     */
    public function getActions()
    {
        return $this->actions;
    }

    /**
     * @param GenericCollection|Action[] $actions
     *
     * @return self
     */
    public function setActions(GenericCollection $actions): self
    {
        $this->actions = $actions;

        return $this;
    }
}
