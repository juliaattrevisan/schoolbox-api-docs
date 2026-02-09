<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter\Page;

use DomainException;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Filter;
use Schoolbox\Role;

/**
 * Represents a filter instance on a page
 */
class Model
{
    use Id;

    // Pages with configurable filters
    // These are enums in DB, you will need to update column definition if you add to this list
    public const string PAGE_PENDING_ASSESSMENTS = 'pending';
    public const string PAGE_GRADES              = 'grades';
    public const string PAGE_CLASS_MARK_BOOK     = 'classMarkbook';          // AKA 'teacher markbook'
    public const string PAGE_COURSE_MARK_BOOK    = 'courseMarkbook';
    public const string PAGE_RUBRIC_MARK_BOOK    = 'rubricMarkbook';
    public const string PAGE_LEARNING_ANALYTICS  = 'analytics';
    public const string PAGE_UPCOMING_ASSESSMENTS = 'dueWorkCal';
    public const string PAGE_ASSESSMENT_CALENDAR = 'assessmentCal';
    public const string PAGE_BADGE_LIST          = 'badgeList';
    public const string PAGE_AWARD_LIST          = 'awardList';
    public const string PAGE_CLASS_PASTORAL      = 'classPastoral';
    public const string PAGE_DIFFERENTIATION_PROFILE = 'differentiationProfile';
    public const string PAGE_CUSTOMISATION_RECORD = 'customisationRecord';


    /**
     * Returns the set of pages that display
     * a list of folders related to the viewee
     *
     * @return string[]
     */
    public static function getInvolvedFoldersPages(): array
    {
        return [
            self::PAGE_PENDING_ASSESSMENTS,
            self::PAGE_UPCOMING_ASSESSMENTS,
        ];
    }


    /**
     * Returns the set of pages where the Controller's public API method has a Folder\Model in the route args
     *
     * @return string[]
     */
    public static function getSingleFolderPages(): array
    {
        return [
            self::PAGE_GRADES,
            self::PAGE_RUBRIC_MARK_BOOK,
            self::PAGE_CLASS_MARK_BOOK,
        ];
    }


    /**
     * @param string $page
     * @return string[]
     */
    public static function getRoleTypesForPage(string $page): array
    {
        return self::getPageRoleTypes()[$page];
    }


    /**
     * Returns a list of roleTypes that can view each page
     *
     * @return string[][]
     */
    public static function getPageRoleTypes(): array
    {
        return [
            self::PAGE_PENDING_ASSESSMENTS  => [
                Role::STAFF,
            ],
            self::PAGE_GRADES               => [
                Role::STAFF,
                Role::PARENT,
                Role::STUDENT,
            ],
            self::PAGE_CLASS_MARK_BOOK      => [
                Role::STAFF,
            ],
            self::PAGE_COURSE_MARK_BOOK     => [
                Role::STAFF,
            ],
            self::PAGE_RUBRIC_MARK_BOOK     => [
                Role::STAFF,
            ],
            self::PAGE_LEARNING_ANALYTICS   => [
                Role::STAFF,
            ],
            self::PAGE_UPCOMING_ASSESSMENTS => [
                Role::STAFF,
                Role::PARENT,
                Role::STUDENT,
            ],
            self::PAGE_ASSESSMENT_CALENDAR => [
                Role::STAFF,
            ],
            self::PAGE_BADGE_LIST => [
                Role::STAFF,
                Role::PARENT,
                Role::STUDENT,
            ],
            self::PAGE_AWARD_LIST => [
                Role::STAFF,
            ],
            self::PAGE_CLASS_PASTORAL => [
                Role::STAFF,
            ],
            self::PAGE_DIFFERENTIATION_PROFILE => [
                Role::STAFF,
            ],
            self::PAGE_CUSTOMISATION_RECORD => [
                Role::STAFF,
            ],
        ];
    }

    /**
     * @var Filter\Model
     */
    private $filter;

    /**
     * Which page does this option relate to?
     *
     * @var string
     */
    private $page;


    /**
     * Is this page potentially filtered by this filter?
     *
     * @var bool
     */
    private $enabled;

    /**
     * Can the end-user see and configure this page filter?
     *
     * @var bool
     */
    private $userConfigurable;


    /**
     * @var GenericCollection|Option\Model[]
     */
    private $options;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->options = new GenericCollection(Option\Model::class);
    }


    /* Getters / Setters
     */


    /**
     * @return Filter\Model
     */
    public function getFilter()
    {
        return $this->filter;
    }


    /**
     * @param Filter\Model $filter
     * @return $this
     */
    public function setFilter(Filter\Model $filter)
    {
        $this->filter = $filter;
        return $this;
    }


    /**
     * @return string
     */
    public function getPage()
    {
        return $this->page;
    }


    /**
     * @param string $page
     * @return $this
     */
    public function setPage($page)
    {
        $this->page = $page;
        return $this;
    }


    /**
     * @return bool
     */
    public function getEnabled()
    {
        return $this->enabled;
    }


    /**
     * @param bool $enabled
     * @return $this
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
        return $this;
    }


    /**
     * @return bool
     */
    public function getUserConfigurable()
    {
        return $this->userConfigurable;
    }


    /**
     * @param bool $userConfigurable
     * @return $this
     */
    public function setUserConfigurable($userConfigurable)
    {
        $this->userConfigurable = $userConfigurable;
        return $this;
    }


    /**
     * @return GenericCollection|Option\Model[]
     */
    public function getOptions()
    {
        return $this->options;
    }


    /**
     * @param GenericCollection|Option\Model[] $options
     * @return $this
     * @throws DomainException
     */
    public function setOptions(GenericCollection $options)
    {
        $this->options->replaceWith($options);
        return $this;
    }
}
