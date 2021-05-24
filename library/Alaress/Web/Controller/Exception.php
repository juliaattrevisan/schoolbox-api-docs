<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Web\Controller;

use ErrorException;

/**
 * Class Exception
 *
 * @package Alaress\Web\Controller
 */
class Exception extends ErrorException
{
    /** @var array */
    protected $reasons;


    /**
     * Controller Exception constructor
     *
     * @param string $problem
     * @param array  $reasons
     * @param int    $returnCode
     */
    public function __construct($problem, array $reasons = [], $returnCode = 400)
    {
        parent::__construct($problem, $returnCode, E_ERROR);
        $this->reasons = $reasons;
    }


    /**
     * @return array
     */
    public function getReasons()
    {
        return $this->reasons;
    }

    /**
     * @return array
     */
    public function contextualize()
    {
        return [
            'problem' => $this->message,
            'reasons' => $this->reasons
        ];
    }
}
