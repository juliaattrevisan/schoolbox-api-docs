<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Survey\Component\Eportfolio;

use Component_Eportfolio_ControllerAbstract;

use Schoolbox\Survey\Mapper as SurveyMapper;

class Controller extends Component_Eportfolio_ControllerAbstract
{
    /**
     * @var mixed
     */
    protected $source;

    /**
     * Loads the grid component into its set configuration.
     *
     * @return true
     */
    public function execute()
    {
        if (is_numeric($this->getConfig('src'))) {
        }

        return true;
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        // Testing craptastic mapper

        var_dump(SurveyMapper::getInstance()->findAll());

        return $this->renderTwigTemplate(
            $templateHandle,
            '@surveyEportfolio/component.twig',
            [
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName(),
                'compact'               => $this->getConfig('compactMode','value')
            ]
        );
    }
}