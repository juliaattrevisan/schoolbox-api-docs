<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Ruleset;

use Schoolbox\Sync\Hydrator;
use Schoolbox\Sync\Rule;

/**
 * A ruleset.
 *
 * @package Schoolbox\Sync\Ruleset
 */
class Ruleset
{
    /**
     * A set of admin-built rules to run.
     *
     * @var Rule\Model[]
     */
    protected $rules;

    /**
     * A set of custom rules to run.
     *
     * @var Hydrator\Custom\Model[]
     */
    protected $customRules;

    /**
     * @var Config\Model
     */
    protected $config;

    /**
     * Constructor
     *
     * @param Rule\Model[]            $rules
     * @param Hydrator\Custom\Model[] $customRules
     * @param Config\Model            $config
     */
    public function __construct($rules, $customRules, Config\Model $config)
    {
        $this->rules       = $rules;
        $this->customRules = $customRules;
        $this->config      = $config;
    }

    /**
     * @return Rule\Model[]
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * @return Hydrator\Custom\Model[]
     */
    public function getCustomRules()
    {
        return $this->customRules;
    }

    /**
     * @return Config\Model
     */
    public function getConfig()
    {
        return $this->config;
    }
}