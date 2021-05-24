<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\ParticipationReport;

/**
 * Represents an award given to a student
 */
class Award
{

    /** @var string */
    private $name;

    /** @var null|string */
    private $detail;


    /**
     * @param string      $name
     * @param string|null $detail
     */
    public function __construct(string $name, ?string $detail)
    {
        $this->name   = $name;
        $this->detail = $detail;
    }


    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * @return null|string
     */
    public function getDetail(): ?string
    {
        return $this->detail;
    }
}
