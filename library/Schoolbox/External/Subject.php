<?php

/**
 * Generic subject object for Schoolbox.
 *
 * Will eventually be associated with a category.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External;

use Schoolbox\IModel;
use Schoolbox\Mapper\Traits\Model\CreateCollection;

class Subject implements IModel
{
    use CreateCollection;

    private ?int $folderId;

    private string $name;

    private ?string $code;

    private ?string $campusCode;

    private ?string $matchedCode;

    private ?string $classType;

    /**
     * @param string|null $code
     * @return self
     */
    public function setCode(?string $code): self
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param int|null $id
     * @return self
     */
    public function setFolderId(?int $id): self
    {
        $this->folderId = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getFolderId(): int
    {
        return $this->folderId;
    }

    /**
     * @param $name
     * @return self
     */
    public function setName($name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getCampusCode(): string
    {
        return $this->campusCode;
    }

    /**
     * @param string $campusCode
     * @return self
     */
    public function setCampusCode(string $campusCode): self
    {
        $this->campusCode = $campusCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getClassType(): string
    {
        return $this->classType;
    }

    /**
     * @param string $classType
     * @return self
     */
    public function setClassType(string $classType): self
    {
        $this->classType = $classType;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getMatchedCode(): ?string
    {
        return $this->matchedCode;
    }

    /**
     * @param string|null $matchedCode
     * @return self
     */
    public function setMatchedCode(?string $matchedCode): self
    {
        $this->matchedCode = $matchedCode;
        return $this;
    }
}
