<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Alaress\Session\Session;
use Schoolbox\Bookmark\Mapper as BookmarkMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Menu\Side\Mapper as MenuItemMapper;
use Schoolbox\Menu\Side\Section\Mapper as MenuSectionMapper;
use Schoolbox\Menu\Side\Section\Model as MenuSideSection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

/**
 * Generate data for rendering the side menu.
 */
class SideMenu implements IDataGenerator
{
    /**
     * @var User|null
     */
    private $currentUser;


    /**
     * @var IConfigLoader
     */
    private $config;


    /**
     * @var Session
     */
    private $session;


    /**
     * @var MenuSectionMapper
     */
    private $menuSectionMapper;


    /**
     * @var MenuItemMapper
     */
    private $menuItemMapper;


    /**
     * @var BookmarkMapper
     */
    private $bookmarkMapper;


    /**
     * Constructor
     *
     * @param IConfigLoader     $config
     * @param null|User         $currentUser
     * @param Session           $session
     * @param MenuSectionMapper $menuSectionMapper
     * @param MenuItemMapper    $menuItemMapper
     * @param BookmarkMapper    $bookmarkMapper
     */
    public function __construct(
        IConfigLoader $config,
        ?User $currentUser,
        Session $session,
        MenuSectionMapper $menuSectionMapper,
        MenuItemMapper $menuItemMapper,
        BookmarkMapper $bookmarkMapper
    ) {
        $this->config            = $config;
        $this->currentUser       = $currentUser;
        $this->session           = $session;
        $this->menuSectionMapper = $menuSectionMapper;
        $this->menuItemMapper    = $menuItemMapper;
        $this->bookmarkMapper    = $bookmarkMapper;
    }


    /**
     * @inheritdoc
     */
    public function generate(?array $chromeData = []): array
    {
        // Return nothing if no user logged in
        if (!$this->currentUser) {
            return [];
        }

        // Mangle subjects into usable format

        $subjectGroups = [];

        // TODO eradicate this session dependency
        // Shouldn't it be in $currentUser?
        $subjects = $this->session->get('subjects');
        if (\is_iterable($subjects)) {
            foreach ($subjects as $subject) {
                if($subject['perm'] >= UserAccess::ACCESS_READ) {
                    if (!isset($subjectGroups[$subject['classType']])) {
                        $subjectGroups[$subject['classType']] = [];
                    }
                    $subjectGroups[$subject['classType']][] = $subject;
                }
            }
            uksort($subjectGroups, function ($a, $b) {
                return strnatcmp($a, $b);
            });
        }

        // Sort the subjects within each subjectGroup
        foreach ($subjectGroups as $subjectGroup => $subjects) {

            // Each value is an array, so sort each array
            usort($subjects, function ($a, $b) {
                return strnatcmp($a['name'] . $a['code'], $b['name'] . $b['code']);
            });

            // Need to assign back, as $subjects is localised variable
            $subjectGroups[$subjectGroup] = $subjects;
        }

        // Get the menu sections in their correct order

        $sections = $this->menuSectionMapper->findAll()->sortBy('sortOrder', 'asc');

        // If there are no title overrides, use the default titles for each section

        /** @var MenuSideSection $section */
        foreach ($sections as $section) {
            if (empty($section->getTitle())) {
                $section->setTitle(
                    $this->menuSectionMapper::getSectionNameDefaults()[$section->getSection()]
                );
            }
        }

        try {
            $nodes = $this->menuItemMapper->findNodesForUser($this->currentUser);
        } catch (MapperException $e) {
            $nodes = [];
        }

        // Order the nodes by their parent ID
        $nodes = $this->menuItemMapper->getCollectionAsTree($nodes);

        // Render this thing out
        // TODO eradicate this session dependency for children
        // Shouldn't it be in $currentUser?

        return [
            'user'      => $this->currentUser,
            'nodes'     => $nodes,
            'subjects'  => $subjectGroups,
            'sections'  => $sections,
            'groups'    => $this->menuItemMapper->findGroupsForUser($this->currentUser),
            'bookmarks' => $this->bookmarkMapper->findByColumn('owner', $this->currentUser->getId()),
            'siteTitle' => $this->config->get('title'),
            'children'  => $this->session->get('children', []),
        ];
    }
}
