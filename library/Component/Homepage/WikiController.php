<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_WikiController extends Component_Homepage_ControllerAbstract {

    /**
     * @var array
     */
    private $source;

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var Category
     */
    private $catHandler;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {

        $this->groupHandler = new Group();
        $this->catHandler = new Category();

        // remove any non-numeric values from the source config
        $this->source = $this->catHandler->getByID(
            $this->getConfig('src') ?
                preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')) :
                $this->getContext('id')
        );

	    if($this->source === false) {
		    $this->source = $this->catHandler->getByID($this->getContext('id'));
	    }

        // specify the sorting fields this module uses
        $this->sortFields = array('Heading', 'Created', 'Modified');

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'wiki')) {
            return false;
        }
        $this->parseConfig();

	    if($this->groupHandler->getPermission($this->source['id']) < READ_ACCESS) {
		    return false;
	    }

	    $this->setBlock($templateHandle, 'wiki_row', 'wiki_row_block');
        $this->setBlock($templateHandle, 'submit_wiki', 'submit_wiki_block');
        $this->setBlock('wiki_row', 'compact_hide', 'compact_hide_block');
        $this->setBlock('compact_hide', 'modify_wiki', 'modify_wiki_block');

        // Custom sorting options


        $wikiHandler = new Wiki();
        $userHandler = new User();

        // Set up component name
        $this->setVar(array(
            'COMPONENT_NAME' => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : 'Wiki',
        ));

        /* Prevent multiple blog entries from other categories stacking up in the wrong place
        * and initialise empty wiki roll by default
        */
        $this->setVar('wiki_row_block', '');

        $wikiList = $wikiHandler->getByCategory($this->source['id'], strtolower($this->getConfig('sort')), $this->getConfig('sortdir'), $this->getConfig('size'));

        $this->setVar(array(
            'WIKI_CATEGORY_ID' => $this->source['id']
        ));

        // Controls such as Post News
        if ($this->permission < WRITE_ACCESS) {
            $this->setVar("submit_wiki_block", "");
        }
        else {
            $this->parse("submit_wiki_block", "submit_wiki");
        }

        // Iterate
        if (is_array($wikiList)) {
            $j = 0;
            foreach ($wikiList as $wiki) {
                $modifiedTime = new Alaress_Date_Relative($wiki['modified']);

                $this->setVar(array(
                    'WIKI_ID' => $wiki['wikiID'],
                    'WIKI_CATEGORYID' => $wiki['categoryID'],
                    'WIKI_SLUG' => $wiki['slug'],
                    'WIKI_HEADING' => Scrub::text($wiki['heading']),
                    'WIKI_AUTHOR' => $userHandler->formatName($wiki),
                    'WIKI_AUTHOR_ID' => $wiki['userID'],
                    'WIKI_MODIFIED' => $modifiedTime->getRelativeTime(),
                    'ON_OFF' => ++$j % 2 ? '' : 'dark',
                ));

                if($this->getConfig('compactMode')) {
                    $this->setVar('compact_hide_block', '');
                }
                else {
                    $this->parse('compact_hide_block', 'compact_hide');
                }

                $this->setVar(array(
                    'modify_wiki_block' => ''
                ));

                if (($this->permission >= READ_ACCESS && $wiki['locked'] == 'false') || $this->permission >= WRITE_ACCESS) {
                    $this->parse('modify_wiki_block', 'modify_wiki');
                }

                $this->parse('wiki_row_block', 'wiki_row', true);
            }
        }

        return true; // everything went according to plan

    }

}
