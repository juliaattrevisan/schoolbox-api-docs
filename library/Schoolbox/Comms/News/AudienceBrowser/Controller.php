<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\AudienceBrowser;

use Alaress\Web\Controller\Base;
use Alaress\Web\Controller\Exception;
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
     * @since 18.1
     * @throws UnknownEntityTypeException
     * @throws \Alaress\Database\Exception
     * @throws UnexpectedValueException
     */
    public function getSearch(): void
    {
        $codec            = new Audience\Segment\Codec();
        $simpleStrategies = $this->strategyFactory->makeAllSimpleStrategies();
        $placeholder      = 'keyword';
        $pieces           = [];

        foreach ($simpleStrategies as $strategy) {
            if ($strategy->canAudienceToEntityType($this->getUser())) {
                $sql = $strategy->getAudiencingSql($this->getUser(), $placeholder);
                if ($sql) {
                    $pieces[] = "({$sql})";
                }
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
            $meta         = $row['entity_meta'];
            $encodedValue = $codec->encodeValue($value);

            foreach ($simpleStrategies[$entityType]->getValidRelationshipModifiers($meta) as $modifier) {
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
     * @throws Exception
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

        $codec = new Audience\Segment\Codec();

        // Special case for folder, only show involved folders in the list
        if (Audience\Segment\Strategy\Folder::getEntityType() === $entityType) {
            $involvedFolders = $this->folderMapper->findForUser($this->getUser(), true, UserAccess::ACCESS_WRITE);
            if ($involvedFolders->count()) {
                $valueLabels = [];
                foreach ($involvedFolders as $folder) {
                    $valueLabels[$codec->encodeValue((string)$folder->getId())] = $folder->getNameWithCodes();
                }
            } else {
                // No involved folders? Fall back to folders with WRITE+ access
                $valueLabels = $this->buildValueLabels($strategy, $codec);
            }
        } else {
            // Normal case
            $valueLabels = $this->buildValueLabels($strategy, $codec);
        }

        \uasort($valueLabels, function (string $left, string $right) {
            return \strnatcasecmp($left, $right);
        });

        $data = [
            'availableModifiers' => $strategy->getValidRelationshipModifiers(),
            'canTargetNull'      => $strategy->canAudienceToEntityValue($this->getUser(), null),
            'valueLabels'        => $valueLabels,
        ];
        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }


    /**
     * Builds value labels for a segment strategy
     *
     * @param Audience\Segment\ISimpleStrategy $strategy
     * @param Audience\Segment\Codec           $codec
     * @return array
     */
    private function buildValueLabels(Audience\Segment\ISimpleStrategy $strategy, Audience\Segment\Codec $codec): array
    {
        $valueLabels = [];
        $sql         = $strategy->getAudiencingSql($this->getUser());
        if ($sql) {
            $stmt = $this->db->executeQuery($sql);
            /** @noinspection PhpAssignmentInConditionInspection */
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $valueLabels[$codec->encodeValue($row['id'])] = $row['name'];
            }
        }
        return $valueLabels;
    }
}
