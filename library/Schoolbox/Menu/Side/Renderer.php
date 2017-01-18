<?php
/**
 * Share navigation element for admin pages
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Menu\Side;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Menu\Side\Mapper as MenuSideMapper;
use Schoolbox\Menu\Side\Model as MenuSide;
use Schoolbox\Menu\Side\Section\Mapper as MenuSideSectionMapper;
use Schoolbox\Menu\Side\Section\Model as MenuSideSection;
use Schoolbox\Bookmark\Mapper as BookmarkMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\User;

class Renderer
{
    /** @var MenuSideMapper */
    protected $menuSideMapper;

    /** @var MenuSideSectionMapper */
    protected $menuSideSectionMapper;

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
        $this->setMenuSideSectionMapper(MenuSideSectionMapper::getInstance());
        $this->setBookmarkMapper(BookmarkMapper::getInstance());
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

        try {
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
        } catch (MapperException $e) {
            $nodes = [];
        }

        // Order the nodes by their parent ID
        $nodes = $this->menuSideMapper->getCollectionAsTree($nodes);

        // Render this thing out

        return $twig->render('@menuSide/renderer.twig', array(
            'isStaff' => $user->getRole()->isStaff(),
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
