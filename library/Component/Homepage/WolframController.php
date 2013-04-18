<?php
/**
 * Enables embedding of wolfram alpha widgets into homepages
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class Component_Homepage_WolframController extends Component_Homepage_ControllerAbstract {

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'wolfram')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_wolfram', 'has_wolfram_block');

        /* If no url is specified it might time out -_-
         * So just hide all this instead
         */

        if (strlen(trim($this->getConfig('url')))) {

            /* Pattern match the necessary code out of a url given to us
             * by the Wolfram Alpha
             *
             * <script type="text/javascript" id="WolframAlphaScript200ac3a5f2238fa6dc3f14148ea5cfba" src="http://www.wolframalpha.com/widget/widget.jsp?id=200ac3a5f2238fa6dc3f14148ea5cfba"></script>
             * http://www.wolframalpha.com/widgets/view.jsp?id=200ac3a5f2238fa6dc3f14148ea5cfba
             */
            $pattern = '/(\?|&)id=([a-zA-Z0-9]+)/';

            $matches = array();

            if (preg_match($pattern, $this->getConfig('url'), $matches)) {
                $this->setVar(
                    array(
                        'WIDGET_ID' => $matches[2],
                    )
                );

                $this->parse('has_wolfram_block', 'has_wolfram');
            }
            else {
                $this->setVar('has_wolfram_block', '');
            }
        } else {
            $this->setVar('has_wolfram_block', '');
        }

        return true; // everything went according to plan
    }

}
