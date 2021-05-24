<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami;

use Alaress_Date_Format;

/**
 * Represents a kami session.
 *
 * Intentionally no setters, this object is immutable, being created by Kami
 */
class Session
{

    /** @var string */
    private $viewSessionKey;

    /** @var string */
    private $viewerUrl;

    /** @var Alaress_Date_Format */
    private $expiry;


    /**
     * @param string              $viewSessionKey
     * @param string              $viewerUrl
     * @param Alaress_Date_Format $expiry
     */
    public function __construct(string $viewSessionKey, string $viewerUrl, Alaress_Date_Format $expiry)
    {
        $this->viewSessionKey = $viewSessionKey;
        $this->viewerUrl      = $viewerUrl;
        $this->expiry         = $expiry;
    }


    /**
     * @return string
     */
    public function getViewSessionKey(): string
    {
        return $this->viewSessionKey;
    }


    /**
     * @return string
     */
    public function getViewerUrl(): string
    {
        return $this->viewerUrl;
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getExpiry(): Alaress_Date_Format
    {
        return $this->expiry;
    }
}
