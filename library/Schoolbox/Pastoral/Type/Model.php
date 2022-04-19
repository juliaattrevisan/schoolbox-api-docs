<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Type;

use Alaress\Color\Color;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Pastoral\DocumentClassification\Model as DocumentClassification;
use Schoolbox\Pastoral;
use Schoolbox\Pastoral\Action\Model as Action;
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

    /** @var string|null */
    protected ?string $name = null;


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


    /** @var DocumentClassification|null */
    protected ?DocumentClassification $documentClassification = null;

    /**
     * Optional. Null if not linked to SIS
     *
     * @var null|string
     */
    private ?string $externalCode = null;

    /** @var GenericCollection|Pastoral\SubType\Model[] */
    private GenericCollection $subTypes;

    /**
     * Visibility locked
     * Defaults to false, when created from the admin page.
     */
    private bool $visibilityLocked = false;

    /**
     * Sentiment 'positive', 'neutral', 'negative'
     */
    private string $sentiment = Model::SENTIMENT_NEUTRAL;

    /** @var GenericCollection|Action[] */
    private GenericCollection $actions;

    public function __construct()
    {
        $this->subTypes = new GenericCollection(Pastoral\SubType\Model::class);
        $this->actions  = new GenericCollection(Action::class);
    }


    /**
     * @param DocumentClassification|null $documentClassification
     *
     * @return self
     */
    public function setDocumentClassification(?DocumentClassification $documentClassification): self
    {
        $this->documentClassification = $documentClassification;

        return $this;
    }

    /**
     * @return DocumentClassification|null
     */
    public function getDocumentClassification(): ?DocumentClassification
    {
        return $this->documentClassification;
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
     * @return GenericCollection|Pastoral\SubType\Model[]
     */
    public function getSubTypes(): GenericCollection
    {
        return $this->subTypes;
    }


    /**
     * @param GenericCollection|Pastoral\SubType\Model[] $subTypes
     * @return self
     * @throws DomainException
     */
    public function setSubTypes(GenericCollection $subTypes): self
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
