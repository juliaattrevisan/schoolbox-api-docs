<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Profile\Relation;

use Schoolbox\External\Profile\Model as BaseProfile;

/**
 * Class Model
 *
 * @package Schoolbox\External\Profile\Relation
 */
class Model
{

    /**
     * @var null|Model
     */
    private $childProfile;


    /**
     * @var null|Model
     */
    private $userProfile;


    /**
     * @var string
     */
    private $type;


    /**
     * @return null|BaseProfile
     */
    public function getChildProfile(): ?BaseProfile
    {
        return $this->childProfile;
    }


    /**
     * @param null|BaseProfile $childProfile
     * @return Model
     */
    public function setChildProfile(?BaseProfile $childProfile): self
    {
        $this->childProfile = $childProfile;
        return $this;
    }


    /**
     * @return null|BaseProfile
     */
    public function getUserProfile(): ?BaseProfile
    {
        return $this->userProfile;
    }


    /**
     * @param null|BaseProfile $userProfile
     * @return Model
     */
    public function setUserProfile(?BaseProfile $userProfile): self
    {
        $this->userProfile = $userProfile;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getType(): ?string
    {
        return $this->type;
    }


    /**
     * @param null|string $type
     * @return Model
     */
    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }
}
