<?php
declare(strict_types=1);
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\AudienceBrowser;

use Alaress\Web\Controller\Base;
use Alaress\Web\Output\Html;
use HouseMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience;
use Schoolbox\GenericCollection;
use Alaress\Database\Connector\IConnector;
use Schoolbox\Pastoral;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\UserMapper;
use PDO;
use Schoolbox\ValueLabelInterface;
use Schoolbox\Comms\News;

class Controller extends Base
{

    /** @var IConnector */
    private $db;

    /**
     * @var CampusMapper
     */
    private $campusMapper;

    /**
     * @var RoleMapper
     */
    private $roleMapper;

    /**
     * @var HouseMapper
     */
    private $houseMapper;

    /**
     * @var FolderMapper
     */
    private $folderMapper;

    /**
     * @var Pastoral\Group\Mapper
     */
    private $pastoralGroupMapper;

    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * @var Audience\Mapper
     */
    private $audienceMapper;

    /**
     * @var RoleAccessMapper
     */
    private $roleAccessMapper;

    /** @var News\Policy\Mapper */
    private $policyMapper;

    /**
     * Controller constructor.
     *
     * @param IConnector            $db
     * @param CampusMapper          $campusMapper
     * @param RoleMapper            $roleMapper
     * @param HouseMapper           $houseMapper
     * @param FolderMapper          $folderMapper
     * @param Pastoral\Group\Mapper $pastoralGroupMapper
     * @param UserMapper            $userMapper
     * @param Audience\Mapper       $audienceMapper
     * @param RoleAccessMapper      $roleAccessMapper
     * @param News\Policy\Mapper    $policyMapper
     */
    public function __construct(
        IConnector $db,
        CampusMapper $campusMapper,
        RoleMapper $roleMapper,
        HouseMapper $houseMapper,
        FolderMapper $folderMapper,
        Pastoral\Group\Mapper $pastoralGroupMapper,
        UserMapper $userMapper,
        Audience\Mapper $audienceMapper,
        RoleAccessMapper $roleAccessMapper,
        News\Policy\Mapper $policyMapper
    ) {
        $this->db           = $db;
        $this->campusMapper = $campusMapper;
        $this->roleMapper = $roleMapper;
        $this->houseMapper = $houseMapper;
        $this->folderMapper = $folderMapper;
        $this->pastoralGroupMapper = $pastoralGroupMapper;
        $this->userMapper = $userMapper;
        $this->audienceMapper = $audienceMapper;
        $this->roleAccessMapper = $roleAccessMapper;
        $this->policyMapper = $policyMapper;
    }


    /**
     * Look for all the audience pieces that might somehow match $keyword
     *
     * @api /comms/news/audiencebrowser/search
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function getSearch(): void
    {
        $searchResult = [];

        /** @var Html $outputFormatter */
        $outputFormatter = $this->getDi()->get('webOutput:nochrome');

        $registry = new Audience\Segment\SearchRegistry($this->getUser(), $this->folderMapper, $this->roleAccessMapper, $this->isGlobalModerationEnabled());
        $translator = new Audience\Segment\Translator($this->folderMapper->getDatabase(), $this->getUser(), $this->folderMapper, $this->roleAccessMapper, $this->isGlobalModerationEnabled());
        $formatter = new Audience\Segment\HtmlFormatter($outputFormatter);

        $placeholder = 'keyword';
        $statement   = $this->userMapper->getDatabase()->prepare($registry->getSearchSql($placeholder));

        $escapedKeyword = $this->db->escapeForLike($this->getRequest()->query['query']);
        $statement->execute([
            $placeholder => "%{$escapedKeyword}%",
        ]);

        /* Set up the RELATIONSHIP_SELF variant first then add Parents and Teachers modifiers
         */
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $data = $this->generateDecoratedDataTuple($row, $translator, $formatter);

            /* One-dimensional stack, please
             */
            $searchResult[] = array_values($formatter->decorate($data));
        }

        $this->getResponse()->content->set($this->buildJsonOutput($searchResult));
    }



    /**
     * @param Collection|ValueLabelInterface[] $collection
     * @param string     $entityType
     * @return \Alaress\Web\Output\Json
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    private function generate(Collection $collection, string $entityType): \Alaress\Web\Output\Json
    {
        $items = [];

        /** @var Html $outputFormatter */
        $outputFormatter = $this->getDi()->get('webOutput:nochrome');

        $translator = new Audience\Segment\Translator($this->folderMapper->getDatabase(), $this->getUser(), $this->folderMapper, $this->roleAccessMapper, $this->isGlobalModerationEnabled());
        $formatter = new Audience\Segment\HtmlFormatter($outputFormatter);

        foreach ($collection as $item) {
            $row = array_combine(['id', 'name'], $item->findValueLabelPair());

            $row['entity_type'] = $entityType;

            $items[] = $this->generateDecoratedDataTuple($row, $translator, $formatter);
        }

        return $this->buildJsonOutput([
            'success' => true,
            'data' => $items
        ]);
    }


    /**
     * Return an (id, name, entityType, modifier, itemHtml) tuple
     *
     * @param array                  $row
     * @param Audience\Segment\Translator     $translator
     * @param Audience\Segment\HtmlFormatter  $formatter
     * @return array
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    private function generateDecoratedDataTuple(array $row, Audience\Segment\Translator $translator, Audience\Segment\HtmlFormatter $formatter): array
    {
        $data = [];

        $baseData = [
            'name' => $row['name'],
            'entityTypeOriginal' => $row['entity_type'],
            'entityType' => $translator->translateEntityType($row['entity_type']),
        ];

        $data[Audience\Model::RELATIONSHIP_SELF] = array_merge($baseData, [
            'id' => Audience\Segment\Codec::encode($row['entity_type'], (string)$row['id'], Audience\Model::RELATIONSHIP_SELF),
            'modifierOriginal' => Audience\Model::RELATIONSHIP_SELF,
            'modifier' => $translator->translateModifier(Audience\Model::RELATIONSHIP_SELF),
        ]);

        /* Only provide the Parents and Teachers modifiers to strats which support them
         * We exclude Parents and Teachers modifiers from Users due to privacy reasons
         *
         * Decide whether entity_type has the modifiers parents_of and teachers_of
         */
        $businessRules = new Audience\BusinessRules();

        $segment = $this->audienceMapper->createModel()
            ->setEntityType($row['entity_type']);

        /* Parents modifier
         */
        if ($businessRules->isValidRelationship($segment, Audience\Model::RELATIONSHIP_PARENTS_OF)) {
            $data[Audience\Model::RELATIONSHIP_PARENTS_OF] = array_merge($baseData, [
                'id' => Audience\Segment\Codec::encode($row['entity_type'], (string)$row['id'], Audience\Model::RELATIONSHIP_PARENTS_OF),
                'modifierOriginal' => Audience\Model::RELATIONSHIP_PARENTS_OF,
                'modifier' => $translator->translateModifier(Audience\Model::RELATIONSHIP_PARENTS_OF),
            ]);
        }

        /* Teachers modifier
         */
        if ($businessRules->isValidRelationship($segment, Audience\Model::RELATIONSHIP_TEACHERS_OF)) {
            $data[Audience\Model::RELATIONSHIP_TEACHERS_OF] = array_merge($baseData, [
                'id' => Audience\Segment\Codec::encode($row['entity_type'], (string)$row['id'], Audience\Model::RELATIONSHIP_TEACHERS_OF),
                'modifierOriginal' => Audience\Model::RELATIONSHIP_TEACHERS_OF,
                'modifier' => $translator->translateModifier(Audience\Model::RELATIONSHIP_TEACHERS_OF),
            ]);
        }

        /* Generate nice html versions of each data point
         */
        return $formatter->decorate($data);
    }


    /**
     * Get (id, name) tuples for every Campus
     *
     * @api /comms/news/audiencebrowser/campuses
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function getCampuses(): void
    {
        $this->getResponse()->content->set($this->generate($this->campusMapper->findAll()->sortBy('name', 'asc'), Audience\Model::ENTITY_TYPE_CAMPUS));
    }


    /**
     * Get (id, name) tuples for every Role
     *
     * @api /comms/news/audiencebrowser/roles
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function getRoles(): void
    {
        $this->getResponse()->content->set($this->generate($this->roleMapper->findAll()->sortBy('name', 'asc'), Audience\Model::ENTITY_TYPE_ROLE));
    }


    /**
     * Get (id, name) tuples for every Pastoral Group
     *
     * @api /comms/news/audiencebrowser/pastoralgroups
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function getPastoralGroups(): void
    {
        $this->getResponse()->content->set($this->generate($this->pastoralGroupMapper->findAll()->sortBy('name', 'asc'), Audience\Model::ENTITY_TYPE_PASTORAL_GROUP));
    }


    /**
     * Get (id, name) tuples for every House
     *
     * @api /comms/news/audiencebrowser/houses
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function getHouses(): void
    {
        $this->getResponse()->content->set($this->generate($this->houseMapper->findAll()->sortBy('house', 'asc'), Audience\Model::ENTITY_TYPE_SCHOOL_HOUSE));
    }


    /**
     * Get (id, name) tuples for every folder
     *
     * @api /comms/news/audiencebrowser/folders
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     * @throws \UnexpectedValueException
     */
    public function getFolders(): void
    {
        $folderStrategy = new Audience\Segment\Strategy\Folder($this->getUser(), $this->folderMapper, $this->roleAccessMapper, $this->isGlobalModerationEnabled());
        $statement      = $this->db->executeQuery($folderStrategy->getSearchSql());
        $searchResults = new GenericCollection(ValueLabelInterface::class);

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $searchResults[$row['id']] = new class($row['id'], $row['name']) implements ValueLabelInterface
            {

                /** @var string */
                private $id;
                /** @var string */
                private $name;


                /**
                 * @param string $id
                 * @param string $name
                 */
                public function __construct(string $id, string $name)
                {
                    $this->id   = $id;
                    $this->name = $name;
                }


                /**
                 * @inheritdoc
                 */
                public function findValueLabelPair(): array
                {
                    return [
                        'value' => $this->id,
                        'label' => $this->name,
                    ];
                }
            };
        }

        $searchResults->sortByCallback(function (ValueLabelInterface $left, ValueLabelInterface $right) {
            // compensate for MySQL lack of natural sort for names like [F1, F9, F10]
            return \strnatcasecmp($left->findValueLabelPair()['label'], $right->findValueLabelPair()['label']);
        }, true);


        $this->getResponse()->content->set($this->generate($searchResults, Audience\Model::ENTITY_TYPE_FOLDER));
    }


    /**
     * Get all year levels
     *
     * @api /comms/news/audiencebrowser/yearlevels
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function getYearLevels(): void
    {
        $items = [];

        /** @var Html $outputFormatter */
        $outputFormatter = $this->getDi()->get('webOutput:nochrome');

        $translator = new Audience\Segment\Translator($this->folderMapper->getDatabase(), $this->getUser(), $this->folderMapper, $this->roleAccessMapper, $this->isGlobalModerationEnabled());
        $formatter = new Audience\Segment\HtmlFormatter($outputFormatter);

        $years = array_filter($this->userMapper->findDistinct('year'));
        sort($years);

        foreach ($years as $year) {
            $row = [
                'id' => $year,
                'name' => \is_numeric($year) ? "Year {$year}" : $year,
                'entity_type' => Audience\Model::ENTITY_TYPE_YEAR
            ];

            $items[] = $this->generateDecoratedDataTuple($row, $translator, $formatter);
        }

        $this->getResponse()->content->set($this->buildJsonOutput([
            'success' => true,
            'data' => $items
        ]));
    }


    /**
     * Returns whether global moderation is enabled
     *
     * @return bool
     */
    private function isGlobalModerationEnabled(): bool
    {
        /** @var News\Policy\Model $policy */
        $policy = $this->policyMapper->findByColumn('identifier', 'global_moderation')->first();
        return (bool)$policy->getValue();
    }
}
