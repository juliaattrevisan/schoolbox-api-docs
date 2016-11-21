<?php
/**
 * Show a list of the users groups on the dashboard
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Folder\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Timetable_ColourMapper;
use Schoolbox\User;
use SitePage;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

class GroupController extends Component_Dashboard_ControllerAbstract
{
    /**
     * @var Collection|FolderModel
     */
    protected $groupList;

    /**
     * @var Array|UserAccess
     */
    protected $userAccess;

    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     */
    public function __construct(
        Component_Instance $instance,
        User $user,
        SitePage $sitepage
    ) {
        parent::__construct($instance, $user, $sitepage);
        $this->setGroupList(new Collection());
    }

    /**
     * Loads the grid component into its set configuration.
     *
     * @return true
     */

    public function execute()
    {
        /* Get Timetable colours to use as random colours for null set folders */
        $colourList = Timetable_ColourMapper::getInstance()->findAll();
        $colourIndex = 0;
        $userAccess  = Array();
        foreach(FolderMapper::getInstance()->findForUser($this->getUser(), false)->sortBy('name', 'asc') as $folder) {
            if (!$folder->getColour()) {
                /* Apply colour from list if no colour exists */
                $folder->setColour($colourList[$colourIndex]->getHexColour());
                $colourIndex = ($colourIndex + 1) % $colourList->count();
            }
            $accessType = Access::getInstance()->getFolderAccess($this->getUser(), [$folder->getId()]);
            switch ($accessType) {
                case UserAccess::ACCESS_READ:
                    $userAccess[$folder->getId()] = Array('read' => true);
                    break;
                case UserAccess::ACCESS_WRITE:
                    $userAccess[$folder->getId()] = Array('write' => true);
                    break;
                case UserAccess::ACCESS_FULL:
                    $userAccess[$folder->getId()] = Array('admin' => true);
                    break;
                default:
                    $userAccess[$folder->getId()] = Array();
                    break;
            }
            $this->getGroupList()->add($folder);
        }
        $this->setUserAccess($userAccess);
        return true;
    }

    /**
     * @param Collection $classList
     */

    public function setGroupList(Collection $groupList)
    {
        $this->groupList = $groupList;
    }

    /**
     * @param Array $userAccess
     */

    public function setUserAccess($userAccess)
    {
        $this->userAccess = $userAccess;
    }

    /**
     * @param String $templateHandle
     *
     * @return bool
     */

    public function renderTemplate($templateHandle)
    {
        if ($this->getGroupList()->count()) {
            return $this->renderTwigTemplate(
                $templateHandle,
                '@folder/Component/groups.twig',
                [
                    'componentInstanceId' => $this->getInstance()->getId(),
                    'componentClass'      => $this->getComponentName(),
                    'currentUser'         => $this->getUser(),
                    'groupList'           => $this->getGroupList(),
                    'userAccess'          => $this->getUserAccess()
                ]
            );

        }

        return false;
    }

    /**
     * @return Collection
     */

    public function getGroupList()
    {
        return $this->groupList;
    }

    /**
     * @return Array
     */

    public function getUserAccess()
    {
        return $this->userAccess;
    }
}
