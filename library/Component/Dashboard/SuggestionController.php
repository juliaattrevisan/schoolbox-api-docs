<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_SuggestionController extends Component_ControllerAbstract {

    const CATEGORY_ID = 1699;

    protected $categoryNews;

    protected $categoryNewsList;

    public function __construct (Component_Instance $instance, User $user, Template $template, CategoryNews $categoryNews) {
        parent::__construct($instance, $user, $template);

        $this->categoryNews = $categoryNews;
    }

    public function execute () {
        $this->categoryNews = $this->categoryNews->getAllForCategory(self::CATEGORY_ID, array( 'size', 1 ));

        return true;
    }

    public function render ($templateHandle) {
        if ( is_array($this->categoryNews) ) {
            $this->setVar('TIPS_TRICKS_HEADING', scrubTEXT($this->categoryNews[0]['heading']));
            $this->setVar('TIPS_TRICKS', scrubHTML($this->categoryNews[0]['body']));

            return true;
        }
        else {
            return false;
        }
    }

}