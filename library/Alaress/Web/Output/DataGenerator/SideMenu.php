<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Schoolbox\Bookmark\Mapper as BookmarkMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Menu\Side\Mapper as MenuItemMapper;
use Schoolbox\Menu\Side\Section\Mapper as MenuSectionMapper;
use Schoolbox\Menu\Side\Section\Model as MenuSideSection;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

/**
 * Generate data for rendering the side menu.
 */
class SideMenu implements IDataGenerator
{
    /**
     * Constructor
     *
     * @param IConfigLoader     $config
     * @param null|User         $currentUser
     * @param MenuSectionMapper $menuSectionMapper
     * @param MenuItemMapper    $menuItemMapper
     * @param BookmarkMapper    $bookmarkMapper
     * @param Access            $access
     */
    public function __construct(
        private IConfigLoader $config,
        private ?User $currentUser,
        private MenuSectionMapper $menuSectionMapper,
        private MenuItemMapper $menuItemMapper,
        private BookmarkMapper $bookmarkMapper,
        private Access $access
    ) {
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

            foreach ($this->currentUser->getSubjectCollection() as $subject) {
                if ($this->access->getFolderAccess($this->currentUser, $subject->getFolderId()) >= UserAccess::ACCESS_READ) {
                    if (!isset($subjectGroups[$subject->getClassType()])) {
                        $subjectGroups[$subject->getClassType()] = [];
                    }
                    $subjectGroups[$subject->getClassType()][] = $subject;
                }
            }
            uksort($subjectGroups, function ($a, $b) {
                return strnatcmp($a, $b);
            });

//        // Sort the subjects within each subjectGroup
//        foreach ($subjectGroups as $subjectGroup => $subjects) {
//            // Each value is an array, so sort each array
//            usort($subjects, function ($a, $b) {
//                return strnatcmp($a['name'] . $a['code'], $b['name'] . $b['code']);
//            });
//
//            // Need to assign back, as $subjects is localised variable
//            $subjectGroups[$subjectGroup] = $subjects;
//        }

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
        return [
            'user'      => $this->currentUser,
            'nodes'     => $nodes,
            'subjects'  => $subjectGroups,
            'sections'  => $sections,
            'groups'    => $this->menuItemMapper->findGroupsForUser($this->currentUser),
            'bookmarks' => $this->bookmarkMapper->findByColumn('owner', $this->currentUser->getId()),
            'siteTitle' => $this->config->get('title'),
            'children'  => $this->currentUser->getChildren(),
        ];
    }
}
