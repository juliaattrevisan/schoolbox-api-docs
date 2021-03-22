<?php
/**
 * Enables embedding of wolfram alpha widgets into homepages
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use Schoolbox\Permission\Access\UserAccess;


class Component_Homepage_WolframController extends Component_Homepage_ControllerAbstract
{
    /**
     * Renders the data into the SitePage. Presentation logic.
     *
     * @param String $templateHandle
     *
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'wolfram')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_widget', 'has_widget_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        $emptyState = false;
        $noConfig = false;

        /* If no url is specified it might time out -_-
         * So just hide all this instead
         */
        if (strlen(trim($this->getConfig('url'))) == 0) {

            $emptyState = true;
            $noConfig = true;

        } else {

            /* Pattern match the necessary code out of a url given to us
             * by the Wolfram Alpha
             *
             * <script type="text/javascript" id="WolframAlphaScript200ac3a5f2238fa6dc3f14148ea5cfba"
             *  src="http://www.wolframalpha.com/widget/widget.jsp?id=200ac3a5f2238fa6dc3f14148ea5cfba"></script>
             * http://www.wolframalpha.com/widgets/view.jsp?id=200ac3a5f2238fa6dc3f14148ea5cfba
             */
            $pattern = '/(\?|&)id=([a-zA-Z0-9]+)/';

            $matches = array();

            $url = $this->getConfig('url');
            if (!preg_match($pattern, $url, $matches)) {
                $emptyState = true;
            }
        }

        if ($emptyState) {
            if (!$this->hasContextWriteAccess()) {
                return false;
            }

            $this->setBlock('empty_state', 'empty_read', 'empty_read_block');
            $this->setBlock('empty_state', 'empty_config', 'empty_config_block');
            $this->setBlock('empty_state', 'empty_invalid', 'empty_invalid_block');

            $this->setVar([
                'has_widget_block' => '',
                'empty_read_block' => '',
                'empty_config_block' => '',
                'empty_invalid_block' => '',
            ]);

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->parse('empty_read_block', 'empty_read');
            } elseif ($noConfig) {
                $this->parse('empty_config_block', 'empty_config');
            } else {
                $this->parse('empty_invalid_block', 'empty_invalid');
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            $this->setVar([
                'empty_state_block' => '',
                'WIDGET_ID' => $matches[2],
            ]);

            // Allow output type to be set too
            $outputType = 'lightbox';
            if (preg_match('/(?:\?|&)output=(?P<output>[a-zA-Z0-9]+)/', $url, $matches)) {
                $outputType = $matches['output'];
            }
            $this->setVar('WIDGET_OUTPUT_TYPE', $outputType);

            $this->parse('has_widget_block', 'has_widget');
        }

        return true; // everything went according to plan
    }
}
