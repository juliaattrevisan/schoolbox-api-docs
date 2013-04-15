<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_RandImageController extends Component_Homepage_ControllerAbstract {

    /**
     * @var DeprecatedDatabase
     */
    private $db;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        $this->db = DeprecatedDatabase::getInstance();
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'randImage')) {
            return false;
        }
        $this->parseConfig();

        //outputs an custom image and link to the homepage
        $this->setBlock($templateHandle, "previewimage", "preview_block");
        $this->setVar('IMAGE_TITLE', 'Random Image');

        //get image id
        $query = "SELECT id, comment
                  FROM file
                  WHERE category = {$this->getContext('id')}
                  AND deleted = '0'
                  AND hidden = '0'
                  AND INSTR(mime_type, 'image/')
                  ORDER BY RAND() LIMIT 1";

        $this->db->query($query);

        if ($image = $this->db->getNextArray()) {
            $pixel = $this->getConfig('pixel') < 20 ? '400' : ($this->getConfig('pixel') > 600 ? 600 : $this->getConfig(
                'pixel'
            ));

            //get link
            $imageurl = "/cms/image.php?id={$image['id']}&height=$pixel&width=$pixel";

            $this->setVar("PREVIEW_IMAGE_URL", $imageurl);
            $this->setVar("PREVIEW_IMAGE_DESC", Scrub::text($image['comment']));

            $this->parse("preview_block", "previewimage");
        } else {
            $this->setVar("preview_block", "");
        }


        return true; // everything went according to plan

    }

}
