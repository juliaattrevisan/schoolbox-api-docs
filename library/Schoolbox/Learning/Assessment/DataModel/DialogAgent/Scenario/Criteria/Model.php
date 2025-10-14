<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */


declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Criteria;

use Schoolbox\ISoftDeletable;
use \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Model as ScenarioModel;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;


    /**
     * @var ScenarioModel|null
     */
    private ?ScenarioModel $scenario = null;


    /**
     * @var string|null
     */
    private ?string $title = null;


    /**
     * @var string|null
     */
    private ?string $prompt = null;


    /**
     * @var int|null
     */
    private ?int $sequence = null;


    /**
     * @return ScenarioModel|null
     */
    public function getScenario(): ?ScenarioModel
    {
        return $this->scenario;
    }


    /**
     * @param ScenarioModel|null $scenario
     * @return $this
     */
    public function setScenario(?ScenarioModel $scenario): self
    {
        $this->scenario = $scenario;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }


    /**
     * @param string|null $title
     * @return $this
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;
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
    public function getSequence(): ?int
    {
        return $this->sequence;
    }


    /**
     * @param int|null $sequence
     * @return $this
     */
    public function setSequence(?int $sequence): self
    {
        $this->sequence = $sequence;
        return $this;
    }
}
