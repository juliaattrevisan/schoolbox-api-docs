<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Ruleset;

use Schoolbox\External\Normalise\User\Base as ExternalUser;
use Schoolbox\Sync\Grammar\Ruleset as GrammarRuleset;
use Schoolbox\Sync\Hydrator;
use Schoolbox\Sync\Rule;
use Schoolbox\User as InternalUser;

/**
 * A ruleset.
 *
 * @package Schoolbox\Sync\Ruleset
 */
class Ruleset implements Hydrator\HydratorInterface
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
     * Optional grammar ruleset
     *
     * @var GrammarRuleset|null
     */
    protected $grammar = null;

    /**
     * Constructor
     *
     * @param Rule\Model[]            $rules
     * @param Hydrator\Custom\Model[] $customRules
     * @param Config\Model            $config
     * @param GrammarRuleset          $grammar
     */
    public function __construct(
        $rules,
        $customRules,
        Config\Model $config,
        GrammarRuleset $grammar = null
    ) {
        $this->rules       = $rules;
        $this->customRules = $customRules;
        $this->config      = $config;
        $this->grammar     = $grammar;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getDescription()
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function hydrate(ExternalUser $input, InternalUser $previous)
    {
        if ($this->getConfig()->getEnabled()) {
            // Previously: I used a grammar factory to create rules
            // Now: the grammar class is embedded at construct time.
            $this->grammar->hydrate($input, $previous);
            foreach ($this->getCustomRules() as $rule) {
                $previous = $rule->getHydrator()->hydrate($input, $previous);
            }
        }
        return $previous;
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