<?php
/**
 * Tile component for dashboard
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Curriculum\Component;

use Component_Homepage_ControllerAbstract;

//use Component_Instance;
//use Group as GroupHandler;
//use Schoolbox\Mapper\Exception as MapperException;
//use Schoolbox\User;
//use SitePage;

class CurriculumInformationController extends Component_Homepage_ControllerAbstract
{
    /**
     * Loads the grid component into its set configuration.
     *
     * @return true
     */
    public function execute()
    {
        return true;
    }

    /**
     * @param String $templateHandle
     *
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        // Make a generic method for Twig based components?
        if (!$this->loadTemplate($templateHandle, 'twig')) {
            return false;
        }
        $this->parseConfig();
        $this->setVar(
            'COMPONENT_CONTENT',
            $this->fetchTwigContent(
                '@curriculum/component/curriculumInformation.twig',
                $this->sitepage
            )
        );
        return true;
    }

}
