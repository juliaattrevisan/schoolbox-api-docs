<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;


/**
 * Responsible for:
 * * Enabling controller to override the user's saved selections. This enables controller to jump to specific view of data
 * * Passing runTime options into components, eg a set of folders taught by the specified user
 */
abstract class AbstractRunTimeOptions implements IRunTimeOptions
{

    /**
     * If non-null, then the controller has specified that we should
     * ignore the user's saved settings, and just select these items.
     *
     * This enables us to jump to a specific data set.
     *
     * @var string[]|null
     */
    private $overrideKeys;


    /**
     * @param string[]|null $overrideKeys
     */
    public function __construct(array $overrideKeys = null)
    {
        if (is_array($overrideKeys)) {
            $this->overrideKeys = count($overrideKeys) ? array_combine($overrideKeys, $overrideKeys) : [];
        }
    }


    /**
     * @inheritdoc
     */
    public function isSelectionOverride()
    {
        // Intentionally does not check for emptiness. This enables controller to clear all selections.
        return is_array($this->overrideKeys);
    }


    /**
     * @return null|string[]
     */
    public function getOverrideKeys()
    {
        return $this->overrideKeys;
    }
}
