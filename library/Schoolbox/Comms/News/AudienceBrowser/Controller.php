<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\AudienceBrowser;

use Alaress\Web\Controller\Base;
use Schoolbox\Comms\News\Audience;
use Alaress\Database\Connector\IConnector;
use Schoolbox\Comms\News\Audience\Segment\StrategyFactory;
use PDO;
use InvalidArgumentException;
use UnexpectedValueException;

class Controller extends Base
{

    /** @var IConnector */
    private $db;

    /** @var Audience\Mapper */
    private $audienceMapper;

    /** @var StrategyFactory */
    private $strategyFactory;


    /**
     * @param IConnector            $db
     * @param Audience\Mapper       $audienceMapper
     * @param StrategyFactory       $strategyFactory
     */
    public function __construct(
        IConnector $db,
        Audience\Mapper $audienceMapper,
        StrategyFactory $strategyFactory
    ) {
        $this->db               = $db;
        $this->audienceMapper   = $audienceMapper;
        $this->strategyFactory  = $strategyFactory;
    }


    /**
     * Look for all the audience pieces that might somehow match $keyword
     *
     * @api   /comms/news/audiencebrowser/search
     * @level 18.1
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     * @throws \Alaress\Database\Exception
     */
    public function getSearch(): void
    {
        $codec           = new Audience\Segment\Codec($this->audienceMapper);
        $strategies      = $this->strategyFactory->makeAll($this->getUser());
        $searchRegistry  = new Audience\Segment\SearchRegistry($this->getUser(), $strategies);
        $placeholder     = 'keyword';
        $statement       = $this->db->prepareQuery($searchRegistry->getSearchSql($placeholder));
        $escapedKeyword  = $this->db->escapeForLike($this->getRequest()->query['query']);

        $statement->execute([$placeholder => "%{$escapedKeyword}%"]);

        $options = [];
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

            $entityType   = $row['entity_type'];
            $value        = $row['id'];
            $name         = $row['name'];
            $encodedValue = $codec->encodeValue($value);

            foreach($strategies[$entityType]->getValidRelationshipModifiers($this->getUser()) as $modifier){
                $key = $codec->encodeSimpleAttributes($entityType, $value, $modifier);
                $option = new Audience\SegmentSelectizeOption($key, $name);
                $option->addLabel($entityType, $encodedValue, $name);
                $options[] = $option;
            }
        }

        $this->getResponse()->content->set($this->buildJsonOutput($options));
    }


    /**
     * Provides a list of available entity values of this entity type for the current user
     *
     * @param string $entityType
     * @throws \Alaress\Web\Controller\Exception
     */
    public function getList(string $entityType): void
    {
        try{
            $strategy = $this->strategyFactory->make($entityType, $this->getUser());
        } catch (InvalidArgumentException $e){
            $this->sendError('Unrecognised entity type', [], 404);
            return;
        }

        if(!$strategy->canBeTargetedByUser($this->getUser())){
            $this->sendError('You cannot audience articles to this audience type', [], 403);
            return;
        }

        $codec         = new Audience\Segment\Codec($this->audienceMapper);
        $stmt          = $this->db->executeQuery($strategy->getSearchSql());
        $valueLabels   = [];

        /** @noinspection PhpAssignmentInConditionInspection */
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            $valueLabels[$codec->encodeValue($row['id'])] = $row['name'];
        }

        \uasort($valueLabels, function (string $left, string $right) {
            return \strnatcasecmp($left, $right);
        });

        $data = [
            'availableModifiers' => $strategy->getValidRelationshipModifiers($this->getUser()),
            'canTargetNull'      => $strategy->canTargetNullEntityValue(),
            'valueLabels'        => $valueLabels,
        ];
        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }
}
