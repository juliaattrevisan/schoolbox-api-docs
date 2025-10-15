<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Field\Context;

use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Field\Model as ScenarioField;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model as DialogAgent;
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
     * @var ScenarioField|null
     */
    private ?ScenarioField $scenarioField = null;


    /**
     * @var DialogAgent|null
     */
    private ?DialogAgent $dialogAgent = null;


    /**
     * @var string|null
     */
    private ?string $body = null;


    /**
     * @return ScenarioField|null
     */
    public function getScenarioField(): ?ScenarioField
    {
        return $this->scenarioField;
    }


    /**
     * @param ScenarioField|null $scenarioField
     * @return $this
     */
    public function setScenarioField(?ScenarioField $scenarioField): self
    {
        $this->scenarioField = $scenarioField;
        return $this;
    }


    /**
     * @return DialogAgent|null
     */
    public function getDialogAgent(): ?DialogAgent
    {
        return $this->dialogAgent;
    }


    /**
     * @param DialogAgent|null $dialogAgent
     * @return $this
     */
    public function setDialogAgent(?DialogAgent $dialogAgent): self
    {
        $this->dialogAgent = $dialogAgent;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getBody(): ?string
    {
        return $this->body;
    }


    /**
     * @param string|null $body
     * @return $this
     */
    public function setBody(?string $body): self
    {
        $this->body = $body;
        return $this;
    }
}
