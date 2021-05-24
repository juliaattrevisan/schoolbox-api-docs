<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification\Parameter;

use JsonSerializable;

interface ParameterInterface extends JsonSerializable
{

    /**
     * Use whichever parameter comes in first. Implies all will be identical.
     */
    const STACKING_IGNORE = -1;

    /**
     * Use the MOST RECENT (highest timestamp) parameter given
     */
    const STACKING_REPLACE = 0;

    /**
     * Use all parameters given by stacking.
     * If you stack more than one parameter per notification,
     * be sure to check edge-cases for unintended side effects.
     */
    const STACKING_APPEND = 1;

    /**
     * Same as STACKING_REPLACE except for no decoration
     */
    const STACKING_REPLACE_NO_DECORATION = 2;

    /**
     * @return string
     */
    public function getText();


    /**
     * @return string
     */
    public function getHref();


    /**
     * @return int
     */
    public function getMode();


    /**
     * Setter for timestamp
     * @param int $timestamp
     */
    public function setTimestamp(int $timestamp): void;


    /**
     * Used as a key to this param, to remove duplicates
     * @return string
     */
    public function getHash();
}
