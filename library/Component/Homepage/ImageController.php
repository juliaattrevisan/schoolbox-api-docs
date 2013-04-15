<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_ImageController extends Component_Homepage_ControllerAbstract {

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
        if(!parent::loadTemplate($templateHandle, 'image')) {
            return false;
        }
        $this->parseConfig();

        //outputs an custom image and link to the homepage
        $this->setBlock($templateHandle, 'previewimage', 'preview_block');
        $this->setVar('IMAGE_TITLE', 'Feature Image');

        //get image id
        $query = "SELECT id, description, comment FROM file WHERE category = '" . $this->getContext('id') . "' AND deleted = '0' AND description LIKE " . $this->db->smartQuote($this->getConfig('image'));
        $image = $this->db->getRow($query);

        if(!is_array($image)) {
            //try to find another image if we cant find the configured one
            $query = "SELECT id, description, comment FROM file WHERE category = " . $this->getContext('id') . " AND deleted = '0' AND INSTR(mime_type, 'image/')";
            $image = $this->db->getRow($query);

            if(!is_array($image)) {
                $this->setVar('preview_block', '');
                return true;
            }
        }

        $this->setVar('IMAGE_TITLE', $image['description']);

            if($this->getConfig('pixel') > 600) {
                $pixel = 600;
            }
            elseif($this->getConfig('pixel') < 20) {
                $pixel = 20;
            }
            else {
                $pixel = $this->getConfig('pixel');
            }

            $this->setVar("PREVIEW_IMAGE_URL", "/cms/image.php?id={$image['id']}&height=$pixel&width=$pixel");
            $this->setVar("PREVIEW_IMAGE_DESC", Scrub::text($image['comment']));

            $url = new Url($this->getConfig('url'));

            if ($url->isUrl()) {
                $this->setVar("CONTENT_URL_START", "<a href=\"" . $url->getBase64EncodedRedirect() . "\" target=\"_blank\">");
                $this->setVar("CONTENT_URL_END", "</a>");
            }
            else {
                $this->setVar("CONTENT_URL_START", "");
                $this->setVar("CONTENT_URL_END", "");
            }

            $this->parse("preview_block", "previewimage");


        //setup block for submission button
        $this->setBlock($templateHandle, 'submit_image', 'submit_image_block');
        if ($this->permission < WRITE_ACCESS)
            $this->setVar('submit_image_block', '');
        else
            $this->parse('submit_image_block', 'submit_image');

        return true; // everything went according to plan

    }

}
