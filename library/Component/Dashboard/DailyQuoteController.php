<?php

/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 * This component will just output a standard set of HTML. Can be used in any scenario you need to output HTML without any processing.
 * To use this component a config option must be set with the component definition with a default value of the template you wish to use
 *
 */


class Component_Dashboard_DailyQuoteController extends Component_Dashboard_ControllerAbstract
{

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        return true;
    }

    /**
     * Outputs HTML template defined by config variable 'template'
     *
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'dailyQuote')) {
            return false;
        }

        $this->parseConfig();

        $factory = new \DailyQuote\Factory();
        $dailyQuote = $factory->produce();

        $this->setVar(array(
           'DAILYQUOTE_DESCRIPTION' => $dailyQuote->getDescription(),
           'DAILYQUOTE_AUTHOR' => $dailyQuote->getAuthor(),
        ));

        return true;
    }
}
