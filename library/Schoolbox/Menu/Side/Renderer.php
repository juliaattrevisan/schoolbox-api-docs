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

class Renderer
{
    /** @var MenuSideMapper */
    protected $menuSideMapper;

    /** @var MenuSideCampusMapper */
    protected $menuSideCampusMapper;

    /** @var MenuSideSectionMapper */
    protected $menuSideSectionMapper;

    /** @var MenuSideRoleMapper */
    protected $menuSideRoleMapper;

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
        $this->configLoader = new ConfigLoader();
    }

    /**
     * @param \Twig_Environment $twig
     * @param User $user
     * @param string $pageCurrent
     * @return null|string
     */
    public function menuSideRenderer(\Twig_Environment $twig, $user, $pageCurrent = null)
    {
        // Return null if no user logged in

        if (!$user instanceof User) {
            return null;
        }

        // Mangle subjects into usable format

        $subjects = [];

        // TODO eradicate this session dependency
        if (is_array($_SESSION["subjects"])) {
            foreach ($_SESSION["subjects"] as $subject) {
                if (!isset($subjects[$subject['classType']])) {
                    $subjects[$subject["classType"]] = [];
                }
                $subjects[$subject['classType']][] = $subject;
            }
        }

        // Get the menu sections in their correct order

        $sections = $this->getMenuSideSectionMapper()->findAll()->sortBy('sortOrder', 'asc');

        // If there are no title overrides, use the default titles for each section

        /** @var MenuSideSection $section */
        foreach ($sections as $section) {
            if (empty($section->getTitle())) {
                $section->setTitle(
                    MenuSideSectionMapper::getSectionNameDefaults()[$section->getSection()]
                );
            }
        }

        $nodes = $this->menuSideMapper->findNodesForUser($user);

        // Primitive tokenization for URLs, allows injecting user IDs and possibly other variables

        /** @var MenuSide $node */
        foreach ($nodes as $node) {

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
        }

        // Render this thing out

        return $twig->render('@menuSide/renderer.twig', array(
            'nodes' => $nodes,
            'subjects' => $subjects,
            'sections' => $sections,
            'pageCurrent' => $pageCurrent,
            'groups' => $this->getMenuSideMapper()->findGroupsForUser($user),
            'bookmarks' => $this->getBookmarkMapper()->findByColumn('owner', $user->getId()),
            'siteTitle' => $this->configLoader->get('title'),
            // TODO eradicate this session dependency
            'children' => is_array($_SESSION['children']) ? $_SESSION['children'] : []
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
}
