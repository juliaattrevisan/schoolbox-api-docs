<?php
/**
 * Show a list of the users classes on the dashboard
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Timetable_ColourMapper;
use Schoolbox\User;
use SitePage;

class ClassController extends Component_Dashboard_ControllerAbstract
{
    /**
     * @var Collection|FolderModel
     */
    protected $classList;

    /**
     * @var Array
     */

    protected $classTypes;

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
        $this->setClassList(new Collection());

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

        $classTypes = Array();

        /* Convert external subjects to folders */
        foreach($this->getUser()->getSubjectCollection() as $subject ) {
            try {
                $folder = FolderMapper::getInstance()->findByCode($subject['code']);
                if (!$folder->getColour()) {
                    /* Apply colour from list if no colour exists */
                    $folder->setColour($colourList[$colourIndex]->getHexColour());
                    $colourIndex = ($colourIndex + 1) % $colourList->count();
                }
                $this->getClassList()->add($folder);
                $classTypes[$folder->getId()] = $subject['classType'];
            } catch (MapperException $e) {
                //not found
            }
        }
        $this->setClassTypes($classTypes);
        return true;
    }

    /**
     * @param String $templateHandle
     *
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        if ($this->getClassList()->count()) {
            return $this->renderTwigTemplate(
                $templateHandle,
                '@learning/component/classes.twig',
                [
                    'componentInstanceId' => $this->getInstance()->getId(),
                    'componentClass'      => $this->getComponentName(),
                    'currentUser'         => $this->getUser(),
                    'currentSemester'     => TermMapper::getInstance()->findForToday(),
                    'classList'           => $this->getClassList(),
                    'classTypes'          => $this->getClassTypes()
                ]
            );

        }

        return false;
    }

    /**
     * @return Collection
     */
    public function getClassList()
    {
        return $this->classList;
    }

    /**
     * @return Array
     */
    public function getClassTypes()
    {
        return $this->classTypes;
    }

    /**
     * @param Collection $classList
     */
    public function setClassList(Collection $classList)
    {
        $this->classList = $classList;
    }

    /**
     * @param Array $classTypes
     */
    public function setClassTypes($classTypes)
    {
        $this->classTypes = $classTypes;
    }

}
