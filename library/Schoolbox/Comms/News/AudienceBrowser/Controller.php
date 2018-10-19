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
use Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use PDO;
use UnexpectedValueException;

class Controller extends Base
{

    /** @var IConnector */
    private $db;

    /** @var Audience\Mapper */
    private $audienceMapper;

    /** @var StrategyFactory */
    private $strategyFactory;

    /** @var Folder\Mapper */
    private $folderMapper;


    /**
     * @param IConnector      $db
     * @param Audience\Mapper $audienceMapper
     * @param StrategyFactory $strategyFactory
     * @param Folder\Mapper   $folderMapper
     */
    public function __construct(
        IConnector $db,
        Audience\Mapper $audienceMapper,
        Folder\Mapper $folderMapper,
        StrategyFactory $strategyFactory

    ) {
        $this->db              = $db;
        $this->audienceMapper  = $audienceMapper;
        $this->folderMapper    = $folderMapper;
        $this->strategyFactory = $strategyFactory;
    }


    /**
     * Look for all the audience pieces that might somehow match $keyword
     *
     * @api   /news/audiencebrowser/search
     * @level 18.1
     * @throws UnknownEntityTypeException
     * @throws \Alaress\Database\Exception
     * @throws UnexpectedValueException
     */
    public function getSearch(): void
    {
        $codec            = new Audience\Segment\Codec($this->audienceMapper);
        $simpleStrategies = $this->strategyFactory->makeAllSimpleStrategies();
        $placeholder      = 'keyword';
        $pieces           = [];

        foreach ($simpleStrategies as $strategy) {
            if ($strategy->canAudienceToEntityType($this->getUser())) {
                $pieces[] = "({$strategy->getAudiencingSql($this->getUser(), $placeholder)})";
            }
        }
        // We can UNION ALL and avoid de-duplication logic as each sub-query has a distinct entity type
        $statement       = $this->db->prepareQuery(\implode(' UNION ALL ', $pieces));
        $escapedKeyword  = $this->db->escapeForLike($this->getRequest()->query['query']);

        $statement->execute([$placeholder => "%{$escapedKeyword}%"]);

        $options = [];
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

            $entityType   = $row['entity_type'];
            $value        = $row['id'];
            $name         = $row['name'];
            $encodedValue = $codec->encodeValue($value);

            foreach ($simpleStrategies[$entityType]::getValidRelationshipModifiers() as $modifier) {
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
            $strategy = $this->strategyFactory->makeSimpleStrategy($entityType);
        } catch (UnknownEntityTypeException $e){
            $this->sendError('Unrecognised entity type', [], 404);
            return;
        }

        if (!$strategy->canAudienceToEntityType($this->getUser())) {
            $this->sendError('You cannot audience articles to this audience type', [], 403);
            return;
        }

        $codec         = new Audience\Segment\Codec($this->audienceMapper);
        $valueLabels   = [];

        // Special case for folder, only show involved folders in the list
        if (Audience\Segment\Strategy\Folder::getEntityType() === $entityType) {
            $involvedFolders = $this->folderMapper->findForUser($this->getUser(), true, UserAccess::ACCESS_WRITE);
            foreach ($involvedFolders as $folder) {
                $valueLabels[$codec->encodeValue((string)$folder->getId())] = $folder->getNameWithCodes();
            }
        } else {
            $stmt = $this->db->executeQuery($strategy->getAudiencingSql($this->getUser()));
            /** @noinspection PhpAssignmentInConditionInspection */
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $valueLabels[$codec->encodeValue($row['id'])] = $row['name'];
            }
        }

        \uasort($valueLabels, function (string $left, string $right) {
            return \strnatcasecmp($left, $right);
        });

        $data = [
            'availableModifiers' => $strategy::getValidRelationshipModifiers(),
            'canTargetNull'      => $strategy->canAudienceToEntityValue($this->getUser(), NULL),
            'valueLabels'        => $valueLabels,
        ];
        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }
}
