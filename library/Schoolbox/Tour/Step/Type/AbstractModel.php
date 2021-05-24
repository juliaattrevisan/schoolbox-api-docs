<?php

/**
 * Created by PhpStorm.
 * User: matt.schembri
 * Date: 16/01/18
 * Time: 8:32 AM
 */

namespace Schoolbox\Tour\Step\Type;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\Uuid;
use Schoolbox\Tour\Step\Model as TourStep;
use Schoolbox\User;

abstract class AbstractModel implements ISoftDeletable, \JsonSerializable
{
    use Uuid;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * @var string|null
     */
    protected $cssClass;

    /**
     * @var TourStep|null
     */
    protected $tourStep;

    /**
     * @var string|null
     */
    protected $body;

    /**
     * @return string|null
     */
    public function getCssClass(): ?string
    {
        return $this->cssClass;
    }

    /**
     * @param string|null $cssClass
     *
     * @return self
     */
    public function setCssClass(?string $cssClass): self
    {
        $this->cssClass = $cssClass;
        return $this;
    }

    /**
     * @return TourStep|null
     */
    public function getTourStep(): ?TourStep
    {
        return $this->tourStep;
    }

    /**
     * @param TourStep|null $tourStep
     *
     * @return self|$this
     */
    public function setTourStep(?TourStep $tourStep): self
    {
        $this->tourStep = $tourStep;
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
     *
     * @return self
     */
    public function setBody(?string $body): self
    {
        $this->body = $body;
        return $this;
    }
}
