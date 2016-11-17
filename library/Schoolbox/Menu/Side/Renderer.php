<?php
/**
 * Share navigation element for admin pages
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Menu\Side;

use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Menu\Side\UserOptions\Mapper as MenuSideUserOptionsMapper;
use Schoolbox\Menu\Side\UserOptions\Model as MenuSideUserOptionsModel;
use Schoolbox\Menu\Side\Mapper as MenuSideMapper;
use Schoolbox\Menu\Side\Model as MenuSide;
use Schoolbox\Menu\Side\Campus\Mapper as MenuSideCampusMapper;
use Schoolbox\Menu\Side\Campus\Model as MenuSideCampus;
use Schoolbox\Menu\Side\Section\Mapper as MenuSideSectionMapper;
use Schoolbox\Menu\Side\Section\Model as MenuSideSection;
use Schoolbox\Menu\Side\Role\Mapper as MenuSideRoleMapper;
use Schoolbox\Menu\Side\Role\Model as MenuSideRole;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Bookmark\Mapper as BookmarkMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\User;

class Renderer extends \Twig_Extension
{
    /** @var MenuSideMapper */
    protected $menuSideMapper;

    /** @var MenuSideCampusMapper */
    protected $menuSideCampusMapper;

    /** @var MenuSideSectionMapper */
    protected $menuSideSectionMapper;

    /** @var MenuSideRoleMapper */
    protected $menuSideRoleMapper;

    /** @var MenuSideUserOptionsMapper */
    protected $menuSideUserOptionsMapper;

    /** @var FolderMapper */
    protected $folderMapper;

    /** @var UserAccessMapper */
    protected $userAccessMapper;

    /** @var ConfigLoader */
    protected $configLoader;

    /** @var BookmarkMapper */
    protected $bookmarkMapper;

    /**
     * Renderer constructor
     */
    public function __construct()
    {
        $this->setMenuSideMapper(MenuSideMapper::getInstance());
        $this->setMenuSideCampusMapper(MenuSideCampusMapper::getInstance());
        $this->setMenuSideSectionMapper(MenuSideSectionMapper::getInstance());
        $this->setMenuSideRoleMapper(MenuSideRoleMapper::getInstance());
        $this->setFolderMapper(FolderMapper::getInstance());
        $this->setBookmarkMapper(BookmarkMapper::getInstance());
        $this->setUserAccessMapper(UserAccessMapper::getInstance());
        $this->setMenuSideUserOptionsMapper(MenuSideUserOptionsMapper::getInstance());
        $this->configLoader = new ConfigLoader();
    }

    /**
     * @return array
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('menuSideRenderer', [$this, 'menuSideRenderer'], [
                'is_safe' => ['html'],
                'needs_environment' => true
            ]),
        );
    }

    /**
     * @param \Twig_Environment $twig
     * @param User $user
     * @param string $pageCurrent
     * @return string
     */
    public function menuSideRenderer(\Twig_Environment $twig, $user, $pageCurrent = null)
    {
        // Check if the user has groups or bookmarks hidden

        if ($user instanceof User) {
            try {
                /** @var MenuSideUserOptionsModel $options */
                $options = $this->getMenuSideUserOptionsMapper()->findOneByColumn("user_id", $user->getId());
                $showBookmarks = $options->getShowBookmarks();
                $showGroups = $options->getShowGroups();
                $showSubjects = $options->getShowSubjects();
            } catch (MapperException $e) {
                $showSubjects = true;
                $showBookmarks = true;
                $showGroups = true;
            }
        } else {
            $showSubjects = false;
            $showBookmarks = false;
            $showGroups = false;
        }

        // Work out what groups the user should have in their menu

        $groupAccess = new Collection;

        if ($showGroups) {
            $groups = $this->getUserAccessMapper()->findAllGroupsForUser($user);

            /** @var UserAccess $group */
            foreach ($groups as $group) {
                try {
                    /** @var FolderModel $folder */
                    $folder = $this->getFolderMapper()->findCheaplyById($group->getServiceIdentifier());

                    $groupAccess->add($folder);
                } catch (MapperException $e) {
                    // Noop
                }
            }
        }

        // Work out what bookmarks the user should have in their menu

        if ($showBookmarks) {
            $bookmarks = $this->getBookmarkMapper()->findByColumn('owner', $user->getId());
        } else {
            $bookmarks = [];
        }

        // Mangle subjects into usable format

        if ($showSubjects) {
            if (is_array($_SESSION["subjects"])) {
                $subjects = [];
                foreach ($_SESSION["subjects"] as $subject) {
                    if (!isset($subjects[$subject['classType']])) {
                        $subjects[$subject["classType"]] = [];
                    }
                    $subjects[$subject['classType']][] = $subject;
                }
            } else {
                $subjects = [];
            }
        } else {
            $subjects = [];
        }

        // Find all menu items and work out if we have permission to view them

        $nodes = $this->getMenuSideMapper()->findAll();

        $nodesRendered = new Collection();

        /** @var MenuSide $node */
        foreach ($nodes as $node) {

            // Check visibility

            // Campuses

            $foundCampus = false;

            $campuses = $this->getMenuSideCampusMapper()->findByColumn('menu_side_id', $node->getId());

            if ($campuses->count() > 0) {

                /** @var MenuSideCampus $campus */
                foreach ($campuses as $campus) {

                    /** @var \Schoolbox\Campus $userCampus */
                    foreach ($user->getCampusCollection() as $userCampus) {
                        if ($campus->getCampusId() == $userCampus->getId()) {
                            $foundCampus = true;
                            break;
                        }
                    }

                    if ($foundCampus) {
                        break;
                    }
                }
            }

            // Campus wasn't found, skip to the next node

            if (!$foundCampus) {
                continue;
            }

            // Groups

            $foundRole = false;

            $roles = $this->getMenuSideRoleMapper()->findByColumn('menu_side_id', $node->getId());

            if ($roles->count() > 0) {

                /** @var MenuSideRole $role */
                foreach ($roles as $role) {

                    if ($role->getRoleId() == $user->getRole()->getId()) {
                        $foundRole = true;
                        break;
                    }
                }

            }

            if (!$foundRole) {
                continue;
            }

            // Primitive tokenization for URLs, allows injecting user IDs and possibly other variables

            $array_find = [
                '[id]',
                '[externalId]',
                '[username]',
                '[firstName]',
                '[lastName]',
            ];

            $array_replace = [
                $user->getId(),
                $user->getExternalId(),
                $user->getUsername(),
                $user->getFirstname(),
                $user->getLastname(),
            ];

            $node->setUrl(str_ireplace($array_find, $array_replace, $node->getUrl()));
            $node->setTitle(str_ireplace($array_find, $array_replace, $node->getTitle()));

            // Add the node to the list that will be rendered

            $nodesRendered->add($node);
        }

        $sections = $this->getMenuSideSectionMapper()->findAll()->sortBy('sortOrder', 'asc');

        /** @var MenuSideSection $section */
        foreach ($sections as $section) {
            if (empty($section->getTitle())) {
                $section->setTitle(MenuSideSectionMapper::getSectionNameDefaults()[$section->getSection()]);
            }
        }

        // Render this thing out

        return $twig->render('@menuSide/renderer.twig', array(
            'pageCurrent' => $pageCurrent,
            'nodes' => $nodesRendered->sortBy('sortOrder', 'asc'),
            'groups' => $groupAccess,
            'showBookmarks' => $showBookmarks,
            'bookmarks' => $bookmarks,
            'siteTitle' => $this->configLoader->get('title'),
            'children' => is_array($_SESSION['children']) ? $_SESSION['children'] : [],
            'subjects' => $subjects,
            'sections' => $sections
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'menu_side_renderer';
    }

    /** Getters and settings ahoy */

    /**
     * @return MenuSideMapper
     */
    public function getMenuSideMapper()
    {
        return $this->menuSideMapper;
    }

    /**
     * @param MenuSideMapper $menuSideMapper
     * @return $this
     */
    public function setMenuSideMapper($menuSideMapper)
    {
        $this->menuSideMapper = $menuSideMapper;
        return $this;
    }

    /**
     * @return MenuSideCampusMapper
     */
    public function getMenuSideCampusMapper()
    {
        return $this->menuSideCampusMapper;
    }

    /**
     * @param MenuSideCampusMapper $menuSideCampusMapper
     * @return $this
     */
    public function setMenuSideCampusMapper($menuSideCampusMapper)
    {
        $this->menuSideCampusMapper = $menuSideCampusMapper;
        return $this;
    }

    /**
     * @return MenuSideSectionMapper
     */
    public function getMenuSideSectionMapper()
    {
        return $this->menuSideSectionMapper;
    }

    /**
     * @param MenuSideSectionMapper $menuSideSectionMapper
     * @return $this
     */
    public function setMenuSideSectionMapper($menuSideSectionMapper)
    {
        $this->menuSideSectionMapper = $menuSideSectionMapper;
        return $this;
    }

    /**
     * @return MenuSideRoleMapper
     */
    public function getMenuSideRoleMapper()
    {
        return $this->menuSideRoleMapper;
    }

    /**
     * @param MenuSideRoleMapper $menuSideRoleMapper
     * @return $this
     */
    public function setMenuSideRoleMapper($menuSideRoleMapper)
    {
        $this->menuSideRoleMapper = $menuSideRoleMapper;
        return $this;
    }

    /**
     * @return FolderMapper
     */
    public function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @param FolderMapper $folderMapper
     * @return $this
     */
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }

    /**
     * @return UserAccessMapper
     */
    public function getUserAccessMapper()
    {
        return $this->userAccessMapper;
    }

    /**
     * @param UserAccessMapper $userAccessMapper
     * @return $this
     */
    public function setUserAccessMapper($userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
        return $this;
    }

    /**
     * @return BookmarkMapper
     */
    public function getBookmarkMapper()
    {
        return $this->bookmarkMapper;
    }

    /**
     * @param BookmarkMapper $bookmarkMapper
     * @return $this
     */
    public function setBookmarkMapper($bookmarkMapper)
    {
        $this->bookmarkMapper = $bookmarkMapper;
        return $this;
    }

    /**
     * @return MenuSideUserOptionsMapper
     */
    public function getMenuSideUserOptionsMapper()
    {
        return $this->menuSideUserOptionsMapper;
    }

    /**
     * @param MenuSideUserOptionsMapper $menuSideUserOptionsMapper
     * @return $this
     */
    public function setMenuSideUserOptionsMapper($menuSideUserOptionsMapper)
    {
        $this->menuSideUserOptionsMapper = $menuSideUserOptionsMapper;
        return $this;
    }
}