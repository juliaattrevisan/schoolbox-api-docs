<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\L10n;


class Term
{
    /** @var string */
    protected $singular;

    /** @var string */
    protected $plural;

    /** @var bool */
    protected $capitalise;


    /**
     * @param string $singular
     * @param string $plural
     * @param bool   $capitaliseFirst
     */
    public function __construct($singular, $plural, $capitaliseFirst = true)
    {
        $this->singular   = $singular;
        $this->plural     = $plural;
        $this->capitalise = $capitaliseFirst;
    }


    /**
     * Twig sugar for translate
     *
     * @return string
     */
    public function proper()
    {
        return $this->translate(false, true);
    }


    /**
     * Twig sugar for translate
     *
     * @return string
     */
    public function plural()
    {
        return $this->translate(true, false);
    }


    /**
     * Twig sugar for translate
     *
     * @return string
     */
    public function properPlural()
    {
        return $this->translate(true, true);
    }


    /**
     * @param bool $plural
     * @param bool $capital
     * @return string
     */
    public function translate($plural = false, $capital = false)
    {
        $local = ($plural ? $this->plural : $this->singular);

        if ($capital) {
            $local = ucwords($local);
            if (!$this->capitalise) {
                $local = lcfirst($local);
            }
        }

        return $local;
    }

}