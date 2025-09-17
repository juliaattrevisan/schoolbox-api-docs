<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Type;

use Alaress\Color\Color;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Pastoral\TypeAction\Model as Action;
use Schoolbox\Sequential;

abstract class TypeAbstract implements ISoftDeletable, Sequential, \JsonSerializable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\UpdatedBy;
    use ModelTrait\HasSequence;

    /**
     * Sentiment (shared with subtype)
     */
    public const SENTIMENT_POSITIVE = 'positive';
    public const SENTIMENT_NEUTRAL  = 'neutral';
    public const SENTIMENT_NEGATIVE = 'negative';

    public const SENTIMENT_VALUES = [
        self::SENTIMENT_POSITIVE,
        self::SENTIMENT_NEUTRAL,
        self::SENTIMENT_NEGATIVE,
    ];

    /**
     * Pastoral Expiry
     */
    public const PASTORAL_EXPIRY_DEFAULT = null;
    public const PASTORAL_EXPIRY = [
        'never'           => self::PASTORAL_EXPIRY_DEFAULT,
        'end of day'      => 'end of day',
        '24 hours'        => '24 hours',
        '1 week'          => '1 week',
        '2 weeks'         => '2 weeks',
        '1 month'         => '1 month',
        '3 months'        => '3 months',
        '6 months'        => '6 months',
        '12 months'       => '12 months',
        '24 months'       => '24 months',
        'calendar year'   => 'calendar year',
        'academic period' => 'academic period',
        'academic year'   => 'academic year',
    ];

    /** @var string|null */
    protected ?string $name = null;

    /**
     * @var string|null
     */
    protected ?string $entryTip = null;

    /**
     * @var string|null
     */
    protected ?string $template = null;

    /**
     * @var string|null
     */
    protected ?string $templatePrivate = null;

    /**
     * This type's color.
     */
    protected ?Color $color = null;

    /**
     * This type's icon.
     */
    protected ?string $icon = null;

    /**
     * Optional. Null if not linked to SIS
     *
     * @var null|string
     */
    protected ?string $externalCode = null;

    /**
     * Visibility locked
     * Defaults to false, when created from the admin page.
     */
    protected bool $visibilityLocked = false;

    /**
     * Sentiment 'positive', 'neutral', 'negative'
     */
    protected string $sentiment = Model::SENTIMENT_NEUTRAL;

    /**
     * Optional. Default Points for this type.
     *
     * @var null|float
     */
    protected ?float $defaultPoints = null;

    /**
     * Pastoral expiry period
     * @var ?string
     */
    protected ?string $defaultExpiry = Model::PASTORAL_EXPIRY_DEFAULT;

    /** @var GenericCollection */
    protected GenericCollection $typeActions;

    public function __construct()
    {
        $this->typeActions = new GenericCollection(Action::class);
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
     * @return string|null
     */
    public function getEntryTip(): ?string
    {
        return $this->entryTip;
    }

    /**
     * @param string|null $entryTip
     * @return static
     */
    public function setEntryTip(?string $entryTip): self
    {
        $this->entryTip = $entryTip;
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
     * @return static
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
     * @return static
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
     * @return float|null
     */
    public function getDefaultPoints(): ?float
    {
        return $this->defaultPoints;
    }

    /**
     * @param float|null $defaultPoints
     * @return static
     */
    public function setDefaultPoints(?float $defaultPoints): self
    {
        $this->defaultPoints = $defaultPoints;
        return $this;
    }

    /**
     * @return GenericCollection|Action[]
     */
    public function getTypeActions()
    {
        return $this->typeActions;
    }

    /**
     * @param GenericCollection $actions
     *
     * @return self
     */
    public function setTypeActions(GenericCollection $actions): self
    {
        $this->typeActions = $actions;

        return $this;
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
     * @return ?string
     */
    public function getDefaultExpiry(): ?string
    {
        return $this->defaultExpiry;
    }

    /**
     * @param ?string $defaultExpiry
     * @return static
     */
    public function setDefaultExpiry(?string $defaultExpiry): self
    {
        $this->defaultExpiry = $defaultExpiry;
        return $this;
    }
}
