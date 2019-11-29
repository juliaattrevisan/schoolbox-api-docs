<?php
/**
 * Show a list of the users classes on the dashboard
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Component\Dashboard;

use Alaress\Database\Connector\IConnector;
use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Classes\ClassList;
use Schoolbox\Resource\Folder;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Timetable_ColourMapper;
use Schoolbox\User;
use SitePage;
use Alaress_Date_Format;


class ClassController extends Component_Dashboard_ControllerAbstract
{
    /** @var Term */
    private $currentTerm;

    /** @var GenericCollection|Folder\Model[] */
    private $classList;

    /** @var string[] */
    private $classTypes = [];

    /** @var string[][]  CourseSpec titles, indexed by class folder.id, courseSpec.id */
    private $courseSpecs;

    /** @var TermMapper */
    private $termMapper;

    /** @var Timetable_ColourMapper */
    private $timeTableColourMapper;

    /** @var string[] */
    private $homepageUrls = [];


    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     * @throws \InvalidArgumentException
     */
    public function __construct(
        Component_Instance $instance,
        User $user,
        SitePage $sitepage
    ) {
        parent::__construct($instance, $user, $sitepage);
        $this->timeTableColourMapper = $this->getMapperFactory()->get(Timetable_ColourMapper::class);
        $this->termMapper            = $this->getMapperFactory()->get(TermMapper::class);
    }


    /**
     * Loads the grid component into its set configuration.
     *
     * @return true
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function execute()
    {
        if ($this->getUser()->getHideTimetable()){
            return false;
        }

        /** @var IConnector $database */
        $database         = $this->getSitePage()->getDi()->get('databaseInternal');
        $classListFactory = new Folder\ClassListFactory($database);

        $classList = new ClassList(
            $this->getUser(),
            $this->getFolderMapper(),
            $this->timeTableColourMapper,
            $classListFactory
        );
        $gridData = $classList->getClassList($this->termMapper->findForToday(), Alaress_Date_Format::now());

        $this->currentTerm  = $gridData['term'];
        $this->classList    = $gridData['classList'];
        $this->classTypes   = $gridData['classTypes'];
        $this->courseSpecs  = $gridData['courses'];
        $this->homepageUrls = $gridData['homepageUrls'];

        return true;
    }

    /**
     * @param String $templateHandle
     *
     * @return bool
     * @throws \Exception
     */
    public function renderTemplate($templateHandle)
    {
        if (0 === $this->classList->count()) {
            return false;
        }

        return $this->renderTwigTemplate(
            $templateHandle,
            '@learning/component/classes.twig',
            [
                'componentInstanceId' => $this->getInstance()->getId(),
                'componentClass'      => $this->getComponentName(),
                'term'                => $this->currentTerm,
                'classList'           => $this->classList,
                'classTypes'          => $this->classTypes,
                'courses'             => $this->courseSpecs,
                'homepageUrls'        => $this->homepageUrls,
            ]
        );
    }
}
