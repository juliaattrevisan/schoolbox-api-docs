<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Models;

class TimetableSetupContact
{

    /** @var string */
    private $email;

    /** @var string */
    private $fax;

    /** @var string */
    private $name;

    /** @var string */
    private $phone;

    /** @var string */
    private $url;


    /**
     * @param string $email
     * @param string $fax
     * @param string $name
     * @param string $phone
     * @param string $url
     */
    public function __construct(string $email, string $fax, string $name, string $phone, string $url)
    {
        $this->email = $email;
        $this->fax   = $fax;
        $this->name  = $name;
        $this->phone = $phone;
        $this->url   = $url;
    }


    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }


    /**
     * @return string
     */
    public function getFax(): string
    {
        return $this->fax;
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
    public function getPhone(): string
    {
        return $this->phone;
    }


    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }
}
