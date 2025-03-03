<?php

/**
 * Show a list of the users groups on the dashboard
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder\Component\Dashboard;

use Alaress\Color\ColorFactory;
use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\Resource\Folder\FolderColour;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Timetable_ColourMapper;
use Schoolbox\User;
use SitePage;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\RerouteOverrideMapper as RerouteOverrideMapper;

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
        $rerouteOverrideMapper = RerouteOverrideMapper::getInstance();

        /* Get Timetable colours to use as random colours for null set folders */
        $folderColours = new FolderColour(Timetable_ColourMapper::getInstance(), new ColorFactory());
        $userAccess  = array();

        $allFolders = FolderMapper::getInstance()->findForUser($this->getUser(), false, UserAccess::ACCESS_READ, false, true);
        $filteredFolders = new Collection();
        foreach ($allFolders as $folder) {
            $muted = $rerouteOverrideMapper->isRouteMuted((string)$folder->getId(), $this->getUser()->getId());

            if ($folder->getGroup()->getForceFollow()) {
                /* If you muted this folder, but Force Follow was enabled,
                 * include this folder - you're voluntold to know about it
                 */
                $filteredFolders->add($folder);
            } elseif (!$muted) {
                /* Otherwise, if you haven't muted it, include this folder
                 */
                $filteredFolders->add($folder);
            }
        }

        foreach ($filteredFolders->sortBy('name', 'asc') as $folder) {
            if (!$folder->getColour()) {
                $folder->setColour($folderColours->getNewColour());
            }
            $accessType = $this->getAccess()->getFolderAccess($this->getUser(), $folder->getId());
            switch ($accessType) {
                case UserAccess::ACCESS_READ:
                    $userAccess[$folder->getId()] = array('read' => true);
                    break;
                case UserAccess::ACCESS_WRITE:
                    $userAccess[$folder->getId()] = array('write' => true);
                    break;
                case UserAccess::ACCESS_FULL:
                    $userAccess[$folder->getId()] = array('admin' => true);
                    break;
                default:
                    $userAccess[$folder->getId()] = array();
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
