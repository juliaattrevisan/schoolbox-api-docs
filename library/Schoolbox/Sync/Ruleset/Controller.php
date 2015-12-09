<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Ruleset;

use Alaress\Web\Controller\Base;
use \Schoolbox\Sync\Condition;
use \Schoolbox\Sync\Effect;
use \Schoolbox\Sync\Hydrator;
use \Schoolbox\Sync\Effect\Type\Mapper as EffectTypeMapper;
use Schoolbox\Sync\Provisioner\Provisioner;
use \Schoolbox;
use \Schoolbox\Sync\Effect\Fragment\Factory as FragmentFactory;
use \Schoolbox\Sync\Rule\Mapper as RuleMapper;
use OutOfBoundsException;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage;
use Schoolbox\Sync\Log;
use Schoolbox\Sync\Navigator\Navigator;
use Schoolbox\Sync\Navigator\Node;

class Controller extends Base {

    /** @var string */
    protected $basePath;


    /**
     * @return bool|string
     */
    function getBasePath()
    {
        // cache
        return $this->basePath ?: $this->basePath = $this->getRouteIfAuthorized('upsRuleset.intro');
    }


    /**
     * Render intro
     */
    public function intro() {

        /* Breadcrumbs
         */
        $allBreadcrumbs = new Navigator(
            new Node('Home', '/adminv2/home', false),
            new Node('User Provisioning System', $this->getBasePath(), true)
        );

        /* Navigation
         */
        $allNavigation = new Navigator(
            new Node('Student', "{$this->getBasePath()}/student", false),
            new Node('Parent', "{$this->getBasePath()}/parent", false),
            new Node('Staff', "{$this->getBasePath()}/staff", false)
        );

        /* Twig data
         */
        $this->getResponse()->content->set([
            'template' => '@upsRuleset/intro.twig',
            'data' => [
                'allBreadcrumbs' => $allBreadcrumbs->getAllNodes(),
                'allNavigation' => $allNavigation->getAllNodes(),
                'logs'         => $this->findLogs(2)
            ]
        ]);
    }

    /**
     * Ruleset view
     *
     * @param string $rulesetId
     */
    public function read($rulesetId) {

        /* Produce Twig template data based on Role
         */
        $factory = new Factory();

        /* Twig data
         */
        $twigData = $factory->produceTemplateData($rulesetId);
        if ($this->getSession()->getSegment(static::class)->getFlash('hasDryRun')) {
            $twigData['hasDryRun'] = true;
        }
        $this->getResponse()->content->set([
            'template' => '@upsRuleset/ruleset.twig',
            'data'     => $twigData,
        ]);
    }


    /**
     * Find a specific set of logs
     *
     * @param int   $limit   limit the number of results
     * @param array $search  the search params to be passed into findByColumnList()
     * @param array $order   the default order in which to return the results
     * @return array
     */
    protected function findLogs($limit = 0, $search = [], $order = [ 'id' => 'desc' ])
    {
        $options = [ 'order' => $order ];

        if ($limit) {
            $options['limit'] = (int)max(0, $limit);
        }

        $logs = Log\Mapper::getInstance()->findByColumnList($search, $options);

        $logdata = [];

        foreach ($logs as $log) {
            $logdata[$log->getId()] = [
                'Timestamp' => $log->getStorage()->getCreatedAt()->getDatabase(),
                'Role Type' => ucfirst($log->getRoleType()),
                'Dry Run'   => $log->isDry(),
                'Created'   => $log->getUsersCreated(),
                'Updated'   => $log->getUsersUpdated(),
                'Skipped'   => $log->getUsersSkipped()
            ];
        }

        return $logdata;
    }


    /**
     * @api   /adminv2/sync/logs
     * @level 156
     *
     * @return void
     */
    public function logs()
    {
        $allBreadcrumbs = new Navigator(
            new Node('Home', '/adminv2/home', false),
            new Node('Sync (UPS)', $this->getBasePath(), false),
            new Node('Logs', $this->getBasePath() . '/logs', true)
        );

        $this->getResponse()->content->set([
            'template' => '@upsRuleset/log/list.twig',
            'data'     => [
                'allBreadcrumbs' => $allBreadcrumbs->getAllNodes(),
                'logs'           => $this->findLogs()
            ]
        ]);
    }


    /**
     * @api   /adminv2/sync/log/{logId}
     * @level 156
     *
     * @param int $logId
     * @return void
     */
    public function log($logId)
    {
        if ($this->getRequest()->isXhr()) {
            $template = 'lines';
            $format   = 'nochrome';
        } else {
            $template = 'detail';
            $format   = 'html';
        }

        $query    = $this->getRequest()->query;
        $page     = $query->get('p', 1);
        $filter   = $query->get('filter', '');
        $template = "@upsRuleset/log/$template.twig";

        try {
            $model  = Log\Mapper::getInstance()->findById($logId);

            $log = [
                'timestamp' => $model->getStorage()->getCreatedAt(),
                'roleType'  => $model->getRoleType(),
                'dryRun'    => $model->isDry(),
                'created'   => $model->getUsersCreated(),
                'updated'   => $model->getUsersUpdated(),
                'skipped'   => $model->getUsersSkipped(),
                'lines'     => $this->getLogPartial($model->getStorage(), $page, $this->parseFilterString($filter))
            ];

            $allBreadcrumbs = new Navigator(
                new Node('Home', '/adminv2/home', false),
                new Node('Sync (UPS)', $this->getBasePath(), false),
                new Node('Logs', $this->getBasePath() . '/logs', false),
                new Node("{$log['timestamp']->getDatabase()} ({$log['roleType']})", $this->getBasePath() . "/log/$logId", true)
            );

            $data = [
                'id'              => $logId,
                'filterBlacklist' => [ 'rulestring' ],
                'log'             => $log,
                'allBreadcrumbs'  => $allBreadcrumbs->getAllNodes(),
                'filterSummary'   => $filter ? ('Filter applied: ' . str_replace(';', '; ', $filter)) : '',
            ];

            $this->getResponse()->content->set([
                'template' => $template,
                'format'   => $format,
                'data'     => $data
            ]);

        } catch (Mapper\Exception $e) {
            // 404 on the log - we have no aura way of doing this!! :(
            $this->getDi()->get('sitePage')->error(41, '', 404);
            exit;

        } catch (OutOfBoundsException $e) {
            // 400: requested a page that doesn't exist
            if ($this->getRequest()->isXhr()) {
                // just tell them they're naughty, that will be enough
                $this->getResponse()->status->set(400);
                $this->getResponse()->content->set([
                    'format' => 'json',
                    'data'   => $e->getMessage()
                ]);

            } else {
                $this->getResponse()->redirect->to($this->getRouteIfAuthorized('upsRuleset.log', [ 'logId' => $logId ]));
            }
        }
    }


    /**
     *
     * @param Storage\Model $storage
     * @param int           $page
     * @param array         $filter       in the format externalUserId(135);firstName(David)
     * @param int           $linesPerPage
     * @throws OutOfBoundsException
     * @return array
     */
    protected function getLogPartial(Storage\Model $storage, $page = 1, $filter = [], $linesPerPage = 50)
    {
        // read the stream so we don't load 50,000 lines of JSON into memory at once
        $inStream = $storage->readStream();

        // type enforcement
        $page         = (int)max($page, 0);
        $linesPerPage = (int)max($linesPerPage, 1);

        // figure out which records we want
        $firstLine = ($page - 1) * $linesPerPage;
        $lastLine  = $firstLine + $linesPerPage - 1;

        $result = [];

        if ($page == 0) {
            throw new OutOfBoundsException("Page not found: $page");
        }

        // read the file, ignoring the first 0 - $firstLine lines
        for ($i = 0; ($line = fgets($inStream)) && $i <= $lastLine; ++$i) {

            // ignore the first $n lines
            if ($i < $firstLine) {
                continue;
            }

            // rip that newline off the end. like a bandaid.
            $line = trim($line);

            // empty lines don't count toward the total
            if (!strlen($line)) {
                --$i;
                continue;
            }

            // every line is individually JSON-encoded, btw
            $linedata = json_decode($line, true);

            // apply the filter if there is one
            if ($filter) {
                // we'll be reading context for the keypair values
                $context = $linedata['context'];

                $exclude = false;
                // the filter has to pass *all* given keys, not just *any*
                foreach ($filter as $key => $value) {
                    if (!array_key_exists($key, $context) || $context[$key] != $value) {
                        $exclude = true;
                        break;
                    }
                }

                // excluded lines don't count toward the total either
                if ($exclude) {
                    --$i;
                    continue;
                }
            }

            // this line is being used? well, ok then
            $result[] = $linedata;
        }

        if (count($result) == 0) {
            throw new OutOfBoundsException("Page not found: $page");
        }

        return $result;
    }


    /**
     * @param string $filterString  in the format 'externalId(135);firstName(David)'
     * @return array
     */
    protected function parseFilterString($filterString)
    {
        if (preg_match_all("`([a-zA-Z_]+)\(([^\)]+)\);?`", $filterString, $matches)) {

            // get rid of the first (wholestring match) index
            array_shift($matches);

            return array_combine($matches[0], $matches[1]);
        }

        return [];
    }


    /**
     * Dry Run view
     *
     * @param string      $rulesetId
     * @param Provisioner $provisioner
     */
    public function dryRun($rulesetId, Provisioner $provisioner)
    {
        // Redirect back to ruleset page, with either an alert message on the page or in notifications
        $this->getSession()->getSegment(static::class)->setFlash('hasDryRun', true);
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate(
                'upsRuleset.read',
                ['rulesetId' => $rulesetId]
            )
        );
        // Not the best place to put it but it will do for now
        $this->getResponse()->content->set([
            'postOutput' => [
                function() use ($rulesetId, $provisioner) {
                    $this->dryRunCallback($rulesetId, $provisioner);
                }
            ],
        ]);
    }

    /**
     * Performs an actual dry run.
     *
     * @param string      $rulesetId
     * @param Provisioner $provisioner
     */
    public function dryRunCallback($rulesetId, Provisioner $provisioner)
    {
        // perform the actual dry run (only for $rulesetId)
        try {
            $provisioner->run(false, $rulesetId);
        } catch (\Exception $e) {
            // the client has already detached - not much we can do
        }
    }

    /**
     * Update each Rule in the Ruleset
     */
    public function update()
    {
        $postData = $this->getRequest()->post;
        $roleType = $postData['rulesetId'];

        $this->updateConfig($roleType, $postData['config'][$roleType]);

        foreach ($postData['rule'] as $ruleId => $ruleData) {
            $this->updateConditions($ruleId, $ruleData['condition']);
            $this->updateEffects($ruleId, $ruleData['effect']);
        }

        $this->updateRuleSequence(array_keys($postData['rule']));
        $this->updateCustomRules($roleType, $postData['customRule']);

        $this->getResponse()->redirect->to("/adminv2/ups/$roleType");
    }

    /**
     * Update a Rule's sequencing
     *
     * Just do a direct query, to save us fetching and resaving
     * a Rule instance that we never had in the first place
     *
     * @param $sequencing
     */
    protected function updateRuleSequence($sequencing) {

        // TODO DI some more
        $mapper = RuleMapper::getInstance();
        $mapper->updateRuleSequence($sequencing);
    }

    /**
     * @param $roleType
     * @param $config
     */
    protected function updateConfig($roleType, $config)
    {
        // TODO DI all of this
        $mapper = Config\Mapper::getInstance();
        $model  = $mapper->findById($roleType);
        $model->setEnabled($config['enabled']);
        $mapper->save($model);
    }

    /**
     * @param int $ruleId
     * @param array $conditions
     */
    protected function updateConditions($ruleId, $conditions)
    {
        // TODO DI
        $mapper = Condition\Mapper::getInstance();

        if (is_array($conditions)) {
            foreach ($conditions as $conditionId => $conditionData) {

                $conditionType = $conditionData['type'];
                // NOT WORKING
                $conditionArgs = (array)$conditionData['arguments'];

                // ensure this is in the right order
                ksort($conditionArgs);

                // this is pretty hacky and not overly extensible...
                if ($conditionType == 1 && count($conditionArgs) == 3) {
                    if (strlen($conditionArgs[1])) {
                        $conditionArgs[1] = preg_split("`[\r\n]+`", $conditionArgs[1]);
                    }
                    $conditionArgs[2] = (bool)$conditionArgs[2];

                } else {
                    $conditionArgs[0] = (bool)$conditionArgs[0];
                }

                $condition = [
                    'id'                => $conditionId,
                    'rule_id'           => $ruleId,
                    'condition_type_id' => $conditionType,
                    'arguments'         => json_encode($conditionArgs)
                ];

                try {
                    $mapper->save($mapper->hydrate($condition));
                }
                catch (\PDOException $e) {
                }
            }
        }

        // housekeeping - make sure there aren't any remnant invalid conditions flying around
        $mapper->deleteByColumnList([
            'rule_id'   => $ruleId,
            'arguments' => json_encode(null)
        ]);
    }

    /**
     * @param int $ruleId
     * @param array $effects
     */
    protected function updateEffects($ruleId, $effects)
    {
        // TODO DI
        $mapper = Effect\Mapper::getInstance();

        $effectTypeMapper = $mapper->getJoinMapper('effect_type_id');
        $ruleMapper = $mapper->getJoinMapper('rule_id');

        $rule = $ruleMapper->findById($ruleId);

        if (is_array($effects)) {
            foreach ($effects as $effectId => $effectData) {
                $effectArgs = (array)$effectData['arguments'];

                // ensure this is in the right order
                ksort($effectArgs);

                /* Figure out what type it was, and cast accordingly
                 */
                $fragmentFactory = new FragmentFactory($rule->getRoleType());

                /* Forge key/value for Mapped Key/Value Pairs
                 */
                if ($effectTypeMapper->isMap($effectData['type'])) {

                    /* Keep arg[0], that token field
                     */
                    $arg = $effectData['arguments'];

                    /* Forge this into key/value pairs and ram that
                     * into $args as well
                     */
                    $pairs = array_combine($effectData['from'], $effectData['to']);

                    /* array_filter filters out values that equate to false
                     * such as zero (which is valid data...). Use unset pears
                     * approach instead
                     *
                     * http://stackoverflow.com/questions/243132/how-do-you-remove-a-value-that-has-an-empty-key-from-an-associative-array-in-php
                     */
                    unset($pairs[null]);

                    $arg[] = $pairs;
                }
                /* Force cast of boolean arg so it doesn't save in some
                 * other data format.
                 *
                 * Sequential Dependency:
                 * This has to take place after isMap() check because the
                 * Mapped Key/Value Pairs can utilise a boolean field
                 */
                else if ($fragmentFactory->isBoolean($effectData['userProperty'])) {
                    $arg = [(bool)$effectArgs[0]];
                }
                /* Otherwise just save it as given
                 */
                else {
                    $arg = $effectArgs;
                }

                $effect = [
                    'id'             => $effectId,
                    'rule_id'        => $ruleId,
                    'effect_type_id' => $effectData['type'],
                    'user_property'  => $effectData['userProperty'],
                    'arguments'      => json_encode($arg)
                ];

                try {
                    $mapper->save($mapper->hydrate($effect));
                }
                catch (\PDOException $e) {
                }
            }
        }

        // housekeeping - make sure there aren't any remnant invalid effects flying around
        $mapper->deleteByColumnList([
            'rule_id'   => $ruleId,
            'arguments' => json_encode(null)
        ]);
    }


    /**
     * Saves custom rules for this role type.
     *
     * @param string $roleType
     * @param array  $customRules Custom rule options
     */
    protected function updateCustomRules($roleType, $customRules)
    {
        if (!is_array($customRules)) {
            return;
        }

        // TODO DI some more
        $mapper = Hydrator\Custom\Mapper::getInstance();
        /** @var Hydrator\Custom\Model[] $rules */
        $rules  = $mapper->findByColumnList([
            'role_type' => $roleType,
            'id'        => array_keys($customRules),
        ]);
        foreach ($rules as $rule) {
            $ruleParams = $customRules[$rule->getId()];
            $rule->setEnabled($ruleParams['enabled']);
        }
        $mapper->save($rules);
    }
}
