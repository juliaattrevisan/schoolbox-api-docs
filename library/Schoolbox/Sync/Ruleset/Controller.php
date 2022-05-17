<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Ruleset;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Sync\Condition;
use Schoolbox\Sync\Effect;
use Schoolbox\Sync\Effect\Type\Model as EffectType;
use Schoolbox\Sync\Hydrator;
use Schoolbox\Sync\Effect\Type\Mapper as EffectTypeMapper;
use Schoolbox\Sync\Provisioner\Provisioner;
use Schoolbox;
use Schoolbox\Sync\Effect\Fragment\Factory as FragmentFactory;
use Schoolbox\Sync\Rule\Mapper as RuleMapper;
use OutOfBoundsException;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage;
use Schoolbox\Sync\Log;
use Schoolbox\Sync\Navigator\Navigator;
use Schoolbox\Sync\Navigator\Node;
use Schoolbox\Sync\Rule\Model as Rule;

/**
 * Class Controller
 *
 * @package Schoolbox\Sync\Ruleset
 */
class Controller extends Base
{

    /**
     * @var string
     */
    protected $basePath;


    /**
     * @var Schoolbox\File\MimetypeMapper
     */
    protected $mimetypeMapper;


    /**
     * Constructor
     *
     * @param Schoolbox\File\MimetypeMapper $mimetypeMapper
     */
    public function __construct(Schoolbox\File\MimetypeMapper $mimetypeMapper)
    {
        $this->mimetypeMapper = $mimetypeMapper;
    }


    /**
     * @return bool|string
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getBasePath()
    {
        // cache
        return $this->basePath ?: $this->basePath = $this->getRouteIfAuthorized('upsRuleset.intro');
    }


    /**
     * Render intro
     */
    public function intro()
    {

        /* Navigation
         */
        $allNavigation = new Navigator([
            new Node('Student', "{$this->getBasePath()}/student", false),
            new Node('Parent', "{$this->getBasePath()}/parent", false),
            new Node('Staff', "{$this->getBasePath()}/staff", false)
        ]);

        /* Twig data
         */
        $this->getResponse()->content->set([
            'template' => '@upsRuleset/intro.twig',
            'data' => [
                'pageCurrent' => $this->router->generate('upsRuleset.intro'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('upsRuleset.intro')),
                'allNavigation' => $allNavigation->getAllNodes(),
                'logs' => $this->findLogs(3)
            ]
        ]);
    }


    /**
     * Ruleset view
     * @api GET /adminv2/ups/{rulesetId}
     * @param string $rulesetId
     */
    public function read(string $rulesetId)
    {

        /* Produce Twig template data based on Role
         */
        $factory  = new Factory();
        $twigData = $factory->produceTemplateData($rulesetId);

        if ($this->getSession()->getSegment(static::class)->getFlash('hasDryRun')) {
            // Dry run flash msg
            $twigData['hasDryRun'] = true;
        }

        if ($this->getSession()->getSegment(static::class)->getFlash('hasPerformUpdate')) {
            // Live run flash msg
            $twigData['hasPerformUpdate'] = true;
        }

        // Obnoxious message if .log file type doesn't exist
        $twigData['logExtensionExists'] = true;

        try {
            $this->mimetypeMapper->getByFilename('log.log');
        } catch (\Schoolbox\File\Exception $e) {
            // This mimetype don't exist
            $twigData['logExtensionExists'] = false;
        }

        $twigData['pageCurrent'] = $this->router->generate('upsRuleset.intro');
        $twigData['allBreadcrumbs'] = (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('upsRuleset.read', ['rulesetId' => $rulesetId]));

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
                'Timestamp' => $log->getStorage()->getCreatedAt()->getShort(),
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
     * @since 156
     *
     * @return void
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function logs()
    {
        $this->getResponse()->content->set([
            'template' => '@upsRuleset/log/list.twig',
            'data'     => [
                'pageCurrent' => $this->router->generate('upsRuleset.intro'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('upsRuleset.intro')),
                'logs'           => $this->findLogs()
            ]
        ]);
    }


    /**
     * @api   /adminv2/sync/log/{logId}
     * @since 156
     *
     * @param int $logId
     * @return void
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function log($logId)
    {
        $this->logFormat($logId);
    }


    /**
     * @api   /adminv2/ups/log/{logId}.{format}
     * @since 156
     *
     * @param int    $logId
     * @param string $format
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function logFormat($logId, $format = '')
    {
        $linesPerPage = 50;

        if ($this->getRequest()->isXhr()) {
            $template = 'lines';
            $format   = 'nochrome';
        } elseif ($format == 'html') {
            // $linesPerPage = PHP_INT_MAX;
            // $template  = 'html';
            // $format    = 'nochrome';
            $template     = 'line';
            $format       = 'stream';
            $useInlineCSS = true;
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

            if ('stream' === $format) {
                $this->getResponse()->content->set([
                    'header'    => "@upsRuleset/log/stream-header.twig",
                    'template'  => $template,
                    'footer'    => "@upsRuleset/log/stream-footer.twig",
                    'format'    => $format,
                    'data' => [
                        'id'            => $logId,
                        'filterSummary' => $filter ? ('Filter applied: ' . str_replace(';', '; ', $filter)) : '',
                        'useInlineCSS'  => true,
                    ],
                    'callback'  => [ $this, 'streamLog' ],
                    'param_arr' => [
                        'stream'          => $model->getStorage()->readStream(),
                        'filter'          => $this->parseFilterString($filter),
                        'filterBlacklist' => ['rulestring'],
                    ],
                ]);
            } else {
                $log = [
                    'timestamp' => $model->getStorage()->getCreatedAt(),
                    'roleType' => $model->getRoleType(),
                    'dryRun' => $model->isDry(),
                    'created' => $model->getUsersCreated(),
                    'updated' => $model->getUsersUpdated(),
                    'skipped' => $model->getUsersSkipped(),
                    'lines' => $this->getLogPartial($model->getStorage(), $page, $this->parseFilterString($filter), $linesPerPage)
                ];

                $data = [
                    'id' => $logId,
                    'filterBlacklist' => ['rulestring'],
                    'log' => $log,
                    'pageCurrent' => $this->router->generate('upsRuleset.intro'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('upsRuleset.intro')),
                    'filterSummary' => $filter ? ('Filter applied: ' . str_replace(';', '; ', $filter)) : '',
                ];

                if (isset($useInlineCSS) && $useInlineCSS) {
                    $data['useInlineCSS'] = true;
                }

                $this->getResponse()->content->set([
                    'template' => $template,
                    'format'   => $format,
                    'data'     => $data
                ]);
            }
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
     * Reads and returns a line from the stream
     *
     * @param resource $stream
     * @param array $filter
     * @param array $filterBlacklist
     * @param int $counter
     * @return array|bool
     */
    public function streamLog($stream, $filter, $filterBlacklist, $counter)
    {
        $line = fgets($stream);

        if (!$line) {
            return false;
        }

        $lineData = json_decode($line, true);
        $result   = [
            'line' => $lineData,
            'lineNumber' => $counter,
            'filterBlacklist' => $filterBlacklist,
        ];

        // apply the filter if there is one
        if ($filter) {
            // we'll be reading context for the keypair values
            $context = $lineData['context'];

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
                $result['increment_counter'] = false;
                $result['render_item'] = false;
            }
        }

        return $result;
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
     * @throws \Aura\Router\Exception\RouteNotFound
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
            'format' => 'noContent',
            'postOutput' => [
                function () use ($rulesetId, $provisioner) {
                    $this->syncCallback($rulesetId, $provisioner, false);
                }
            ],
        ]);
    }


    /**
     * Perform live UPS run
     *
     * @param string      $rulesetId
     * @param Provisioner $provisioner
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function performUpdate($rulesetId, Provisioner $provisioner)
    {
        // Redirect back to ruleset page, with either an alert message on the page or in notifications
        $this->getSession()->getSegment(static::class)->setFlash('hasPerformUpdate', true);
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate(
                'upsRuleset.read',
                ['rulesetId' => $rulesetId]
            )
        );
        // Not the best place to put it but it will do for now
        $this->getResponse()->content->set([
            'format' => 'noContent',
            'postOutput' => [
                function () use ($rulesetId, $provisioner) {
                    $this->syncCallback($rulesetId, $provisioner, true);
                }
            ],
        ]);
    }


    /**
     * Performs an actual dry run.
     *
     * @param string      $rulesetId
     * @param Provisioner $provisioner
     * @param bool        $performUpdate
     */
    public function syncCallback($rulesetId, Provisioner $provisioner, $performUpdate = false)
    {
        // perform the actual dry run (only for $rulesetId)
        try {
            $provisioner->run($performUpdate, $rulesetId);
        } catch (\Exception $e) {
            // the client has already detached - not much we can do
        }
    }


    /**
     * Update the settings for a Ruleset (student, parent, staff)
     * @api POST /adminv2/ups/ruleset/update
     */
    public function updateRuleset()
    {
        $response = [];
        $postData = $this->getRequest()->post;
        $roleType = $postData['rulesetId'];

        $response['config']     = $this->updateConfig($roleType, $postData['config'][$roleType]);
        $response['sequence']   = $this->updateRuleSequence(($postData['sequence']));
        $response['customRule'] = $this->updateCustomRules($roleType, $postData['customRule'] ?? null);

        $this->getResponse()->content->set($this->buildJsonOutput($response));
    }


    /**
     * Update a single Rule in the Ruleset.
     *
     * Each call to this function must provide the contents for a single Rule in
     * the Ruleset. Saving 'All Rules' would trigger multiple parallel asynchronous
     * calls to this function. This is to avoid max_input_vars being breached.
     *
     * @api POST /adminv2/ups/ruleset/rule/update
     */
    public function updateRule()
    {

        $response = [];
        $postData = $this->getRequest()->post;

        foreach ($postData['rule'] as $ruleId => $ruleData) {
            $response['condition'] = $this->updateConditions($ruleId, $ruleData['condition'] ?? null);
            $response['effect']    = $this->updateEffects($ruleId, $ruleData['effect'] ?? null);
        }

        $this->getResponse()->content->set($this->buildJsonOutput($response));
    }

    /**
     * Update a Rule's sequencing
     *
     * Just do a direct query, to save us fetching and resaving
     * a Rule instance that we never had in the first place
     *
     * @param array $sequencing
     * @return bool Whether Rule Sequence was successfully saved
     * @throws \PDOException
     */
    protected function updateRuleSequence($sequencing)
    {

        // TODO DI some more
        $mapper = RuleMapper::getInstance();
        return false !== $mapper->updateRuleSequence($sequencing);
    }


    /**
     * @param int $roleType
     * @param array $config
     * @return bool Whether config was successfully saved
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    protected function updateConfig($roleType, $config)
    {
        // TODO DI all of this
        $mapper = Config\Mapper::getInstance();
        $model  = $mapper->findById($roleType);
        $model->setEnabled($config['enabled']);
        return false !== $mapper->save($model);
    }


    /**
     * @param int   $ruleId
     * @param array $conditions
     * @return array maps 'new-x' to newly assign id 'y'
     * @throws Mapper\Exception
     * @throws \UnexpectedValueException
     */
    protected function updateConditions($ruleId, $conditions)
    {
        // TODO DI
        $mapper = Condition\Mapper::getInstance();

        // Map 'new-X' conditionId to newly assigned values
        $results = [];

        if (is_array($conditions)) {
            foreach ($conditions as $conditionId => $conditionData) {
                $conditionType = $conditionData['type'];
                // NOT WORKING
                $conditionArgs = (array)$conditionData['arguments'];

                // ensure this is in the right order
                ksort($conditionArgs);

                /**
                 * if the user has not nominated a field, we're not even going to attempt saving/handling it
                 */
                if ($conditionArgs[0] === null || trim($conditionArgs[0]) === '') {
                    continue;
                }

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
                    $model = $mapper->hydrate($condition);
                    $mapper->save($model);
                    $results[$conditionId] = $model->getId();
                } catch (\PDOException $e) {
                }
            }
        }

        // housekeeping - make sure there aren't any remnant invalid conditions flying around
        $mapper->deleteByColumnList([
            'rule_id'   => $ruleId,
            'arguments' => json_encode(null)
        ]);

        return $results;
    }


    /**
     * @param int   $ruleId
     * @param array $effects
     * @return array maps 'new-x' to newly assign id 'y'
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    protected function updateEffects($ruleId, $effects)
    {
        // TODO DI
        $mapper = Effect\Mapper::getInstance();

        // Map 'new-X' conditionId to newly assigned values
        $results = [];

        /** @var EffectTypeMapper $effectTypeMapper */
        $effectTypeMapper = $mapper->getJoinMapper('effect_type_id');
        $ruleMapper = $mapper->getJoinMapper('rule_id');

        $rule = $ruleMapper->findById($ruleId);

        if (is_array($effects)) {
            foreach ($effects as $effectId => $effectData) {
                // Don't save undefined / invalid effects

                // All effects must have a 'type'
                if ('' == $effectData['type']) {
                    continue;
                }

                // All non-'skip' effects must have a 'userProperty'
                if (EffectTypeMapper::EFFECT_TYPE_SKIP != $effectData['type'] && '' == $effectData['userProperty']) {
                    continue;
                }

                $effectArgs = (array)($effectData['arguments'] ?? ['']);

                // ensure this is in the right order
                ksort($effectArgs);

                /* Figure out what type it was, and cast accordingly
                 */
                $fragmentFactory = new FragmentFactory($rule->getRoleType());

                /* Forge key/value for Mapped Key/Value Pairs
                 */
                if ($effectTypeMapper->isMap($effectData['type'])) {
                    /* Keep arg[0], that token field
                     * Default the token field to an empty string
                     * (in case it was saved without a token field)
                     */
                    $arg = $effectData['arguments'] ?? [''];

                    if ($fragmentFactory->isCollection($effectData['userProperty'])) {
                        $to = [];
                        foreach ($effectData['to'] as $k => $v) {
                            $xpl = explode(',', $v);
                            $to[$k] = count($xpl) == 1 ? $xpl[0] : $xpl;
                        }
                    } else {
                        $to = $effectData['to'];
                    }

                    /* Forge this into key/value pairs and ram that
                     * into $args as well
                     * (In either effect data or to field don't exist, consider
                     *  the pairs list empty)
                     */
                    $pairs = array_combine($effectData['from'] ?? [], $to ?? []);

                    /* array_filter filters out values that equate to false
                     * such as zero (which is valid data...). Use unset pears
                     * approach instead
                     *
                     * http://stackoverflow.com/questions/243132/how-do-you-remove-a-value-that-has-an-empty-key-from-an-associative-array-in-php
                     */
                    unset($pairs[null]);

                    $arg[] = $pairs;
                } else if ($fragmentFactory->isBoolean($effectData['userProperty'])) {
                    /* Force cast of boolean arg so it doesn't save in some
                     * other data format.
                     *
                     * Sequential Dependency:
                     * This has to take place after isMap() check because the
                     * Mapped Key/Value Pairs can utilise a boolean field
                     */

                    $arg = [(bool)$effectArgs[0]];
                } elseif ($fragmentFactory->isCollection($effectData['userProperty'])) {
                    /*
                     * Explode the collection type on comma
                     */
                    $xpl = explode(',', $effectArgs[0]);
                    $arg = [count($xpl) == 1 ? $xpl[0] : $xpl];
                } else {
                    /* Otherwise just save it as given
                     */
                    $arg = $effectArgs;
                }

                $effect = [
                    'id'             => $effectId,
                    'rule_id'        => $ruleId,
                    'effect_type_id' => $effectData['type'],
                    'user_property'  => $effectData['userProperty'],
                    'arguments'      => json_encode($arg)
                ];

                // treat skips special-like
                if ($effectTypeMapper->isSkip($effectData['type'])) {
                    $effect['arguments'] = json_encode([]);
                }

                try {
                    $rule = $ruleMapper->findById($effect['rule_id']);
                } catch (Mapper\Exception $e) {
                    $rule = (new Rule())->setId($effect['rule_id']);
                }

                try {
                    $effectType = $effectTypeMapper->findById($effect["effect_type_id"]);
                } catch (Mapper\Exception $e) {
                    $effectType = (new EffectType())->setId($effect["effect_type_id"]);
                }

                try {
                    $model = $mapper->findOneByColumnList([
                        'rule_id'       => $rule->getId(),
                        'user_property' => $effect['user_property'],
                    ]);
                } catch (Mapper\Exception $e) {
                    $model = (new Effect\Model())->setId($effect['id']);
                }

                $model->setRule($rule);
                $model->setEffectType($effectType);
                $model->setUserProperty($effect['user_property']);
                $model->setArguments($arg);
                $mapper->save($model);

                $results[$effectId] = $model->getId();
            }
        }

        // housekeeping - make sure there aren't any remnant invalid effects flying around
        $mapper->deleteByColumnList([
            'rule_id'         => $ruleId,
            'arguments'       => json_encode(null)
        ]);

        return $results;
    }


    /**
     * Saves custom rules for this role type.
     *
     * @param string $roleType
     * @param array  $customRules Custom rule options
     * @return bool Whether Custom Rules were successfully saved
     * @throws Mapper\Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    protected function updateCustomRules($roleType, $customRules)
    {
        if (!is_array($customRules)) {
            return false;
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
        return false !== $mapper->save($rules);
    }
}
