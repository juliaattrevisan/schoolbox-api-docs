<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent;

use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\AssessmentModelTrait;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\CreatedBy;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedBy;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedBy;

class Model implements IAssessableDataModel, ISoftDeletable
{
    use Id;
    use AssessmentModelTrait;
    use CreatedAtDate;
    use CreatedBy;
    use UpdatedAtDate;
    use UpdatedBy;
    use DeletedAtDate;
    use DeletedBy;


    /**
     * @var int|null
     */
    private ?int $scenarioId = null;


    /**
     * @var string|null
     */
    private ?string $language = null;


    /**
     * @var string|null
     */
    private ?string $helpfulness = null;


    /**
     * @var bool|null
     */
    private ?bool $useDifferentiationProfile = null;


    /**
     * @var string|null
     */
    private ?string $openingMessage = null;


    /**
     * @var string|null
     */
    private ?string $rules = null;


    /**
     * @var string|null
     */
    private ?string $prompt = null;


    /**
     * @var int|null
     */
    private ?int $duration = null;


    /**
     * @var \Schoolbox\GenericCollection|null
     */
    private ?\Schoolbox\GenericCollection $fields = null;


    /**
     * @return int|null
     */
    public function getScenarioId(): ?int
    {
        return $this->scenarioId;
    }


    /**
     * @param int|null $scenarioId
     * @return $this
     */
    public function setScenarioId(?int $scenarioId): self
    {
        $this->scenarioId = $scenarioId;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getLanguage(): ?string
    {
        return $this->language;
    }


    /**
     * @param string|null $language
     * @return $this
     */
    public function setLanguage(?string $language): self
    {
        $this->language = $language;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getHelpfulness(): ?string
    {
        return $this->helpfulness;
    }


    /**
     * @param string|null $helpfulness
     * @return $this
     */
    public function setHelpfulness(?string $helpfulness): self
    {
        $this->helpfulness = $helpfulness;
        return $this;
    }


    /**
     * @return bool|null
     */
    public function getUseDifferentiationProfile(): ?bool
    {
        return $this->useDifferentiationProfile;
    }


    /**
     * @param bool|null $useDifferentiationProfile
     * @return $this
     */
    public function setUseDifferentiationProfile(?bool $useDifferentiationProfile): self
    {
        $this->useDifferentiationProfile = $useDifferentiationProfile;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getOpeningMessage(): ?string
    {
        return $this->openingMessage;
    }


    /**
     * @param string|null $openingMessage
     * @return $this
     */
    public function setOpeningMessage(?string $openingMessage): self
    {
        $this->openingMessage = $openingMessage;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getRules(): ?string
    {
        return $this->rules;
    }


    /**
     * @param string|null $rules
     * @return $this
     */
    public function setRules(?string $rules): self
    {
        $this->rules = $rules;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getPrompt(): ?string
    {
        return $this->prompt;
    }


    /**
     * @param string|null $prompt
     * @return $this
     */
    public function setPrompt(?string $prompt): self
    {
        $this->prompt = $prompt;
        return $this;
    }


    /**
     * @return int|null
     */
    public function getDuration(): ?int
    {
        return $this->duration;
    }


    /**
     * @param int|null $duration
     * @return $this
     */
    public function setDuration(?int $duration): self
    {
        $this->duration = $duration;
        return $this;
    }


    /**
     * @return \Schoolbox\GenericCollection|null
     */
    public function getFields(): ?\Schoolbox\GenericCollection
    {
        return $this->fields;
    }


    /**
     * @param \Schoolbox\GenericCollection|null $fields
     * @return $this
     */
    public function setFields(?\Schoolbox\GenericCollection $fields): self
    {
        $this->fields = $fields;
        return $this;
    }
}
