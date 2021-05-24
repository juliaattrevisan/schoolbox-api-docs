<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami;

/**
 * Encapsulates the results of a Kami Uploader's execution
 */
class UploadResults
{

    /** @var int */
    private $countSuccess = 0;

    /** @var int */
    private $countFailure = 0;


    /**
     * @return int
     */
    public function getCountSuccess(): int
    {
        return $this->countSuccess;
    }


    /**
     * @return $this|self
     */
    public function incrementSuccessCount(): self
    {
        $this->countSuccess++;
        return $this;
    }


    /**
     * @return int
     */
    public function getCountFailure(): int
    {
        return $this->countFailure;
    }


    /**
     * @return $this|self
     */
    public function incrementFailureCount(): self
    {
        $this->countFailure++;
        return $this;
    }


    /**
     * @return int
     */
    public function getCountTotal(): int
    {
        return $this->getCountSuccess() + $this->getCountFailure();
    }
}
