<?php
/**
 * Translator
 *
 * Render human-readable representations of segment data
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Alaress\IDatabase;
use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

class Translator
{
    /**
     * Translate entity types into something humanreadable
     *
     * @var array
     */
    private $entityTranslation;

    /**
     * Translate parents_of and teachers_of into something humanreadable
     *
     * @var array
     */
    private $modifierTranslation;

    /**
     * @var IDatabase
     */
    private $database;

    /**
     * @var User
     */
    private $user;

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;

    /**
     * @var RoleAccessMapper
     */
    private $roleAccessMapper;

    /** @var bool */
    private $isModerationEnabled;

    /**
     * This array is of the structure:
     *
         [
             entity_type =>
                 entity_value => [
                     name => '',
                     relationships => []
                 ]
             ]
         ]

         $example = [
            'folder' =>
                '43' => [
                    'name' => 'English 344 (EN344, ABC123, DEF456)',
                    'relationships' => [
                        'parents_of', 'teachers_of'
                    ]
                ],
                '111' => [
                    'name' => 'My Class (MC111)',
                    'relationships' => []
                ]
            ],
            'campus' =>
                '2' => [
                    'name' => 'Big Campus',
                    'relationships' => []
                ]
            ]
        ]

     * @var array
     */
    private $cache = [];


    /**
     * SegmentTranslator constructor.
     *
     * @param IDatabase        $db
     * @param User             $user
     * @param Folder\Mapper    $folderMapper
     * @param RoleAccessMapper $roleAccessMapper
     * @param bool             $isModerationEnabled
     */
    public function __construct(IDatabase $db, User $user, Folder\Mapper $folderMapper, RoleAccessMapper $roleAccessMapper, bool $isModerationEnabled)
    {
        $this->database = $db;
        $this->user = $user;
        $this->folderMapper = $folderMapper;
        $this->roleAccessMapper = $roleAccessMapper;
        $this->isModerationEnabled = $isModerationEnabled;

        /* Entity names
         */
        $this->entityTranslation = array_combine(Model::getSimpleEntityTypes(), [
            'User',
            'Superuser',
            'Campus',
            'Role',
            'Role Type',
            'House',
            'Residential House',
            'Year Level',
            'Service',
            'Pastoral Group',
            'Class/Group'
        ]);

        /* Relationship modifiers
         */
        $this->modifierTranslation = array_combine(Model::getRelationships(), [
            'Self',
            'Parents of students',
            'Teachers of students'
        ]);
    }


    /**
     * Take a schematic entity type such as school_house and decide what a human would see in the ui
     * This doesn't assemble a full audiencing segment
     *
     * @param string $entityType
     * @return string
     */
    public function translateEntityType(string $entityType): string
    {
        return $this->entityTranslation[$entityType];
    }


    /**
     * Take a schematic modifier such as parents_of and decide what a human would see in the ui
     * This doesn't assemble a full audiencing segment
     *
     * @param string $modifier
     * @return string
     */
    public function translateModifier(string $modifier): string
    {
        if ($modifier === Model::RELATIONSHIP_SELF) {
            return '';
        }

        return $this->modifierTranslation[$modifier];
    }


    /**
     * From an entityType and entityValue, determine a human-readable name
     *
     * e.g. school_house 1234 equates to "Dalton House"
     *
     * @param string $entityType
     * @param string $entityValue
     * @return string
     */
    public function translateNameFromEntityType(string $entityType, string $entityValue): string
    {
        return $this->cache[$entityType][$entityValue]['name'];
    }


    /**
     * Take a schematic entity type such as school_house and decide what a human would see in the ui
     * Yes, this is presentation logic, but backend is the cleanest place to put it for now due to other data sources
     * like ajax requests needing a translation service in a single point of control
     *
     * But you can feed it three values for it to assemble a segment (ie. when you're searching for items)
     *
     * Return a string like "User (230) [Parents of]"
     *
     * @param string $name
     * @param string $entityType
     * @param string $relationship
     * @return string
     */
    public function assembleSegment(string $name, string $entityType, string $relationship): string
    {
        $string = "{$name} ({$this->translateEntityType($entityType)})";

        if ($relationship !== Model::RELATIONSHIP_SELF) {
            $string .= " [{$this->modifierTranslation[$relationship]}]";
        }

        return $string;
    }


    /**
     * Translation cache
     * All the audiencing segments and their human-readable names are collected here
     * There are six types: campus role house year folder pastoralgroup
     *
     * We asked dadt for permission, don't worry
     *
     * @param int $articleId
     * @return $this
     */
    public function buildCache(int $articleId): self
    {
        $translator = new TranslatorRegistry($this->user, $this->folderMapper, $this->roleAccessMapper, $this->isModerationEnabled);

        $statement = $this->database->prepare($translator->getTranslateSql());

        $statement->execute([
            'articleId' => $articleId,
        ]);

        /* Build associative array for quick lookup
         */
        $refined = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $refined[$row['entity_type']][$row['entity_value']]['name'] = $row['name'];
            $refined[$row['entity_type']][$row['entity_value']]['relationships'][] = $row['relationship'];
        }

        $this->cache = $refined;

        return $this;
    }

    /**
     * @return array
     */
    public function getCache(): array
    {
        return $this->cache;
    }
}
