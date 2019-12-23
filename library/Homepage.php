<?php

/* This script controls homepage layout functions.  It is used for setting up templates and customising homepage */

use Schoolbox\Resource;

class Homepage
{

    /* Used to remove homepage components and reset template
	 */
    const REMOVE_HOMEPAGE_TEMPLATE_ID = -1;
    const KEEP_EXISTING_HOMEPAGE      = 0;
    /**
     * @var bool|DeprecatedDatabase
     */
    private $db;
    private $cid;

    public function __construct($cid)
    {
        $this->db  = DeprecatedDatabase::getInstance();
        $this->cid = $cid;
    }

    public function getTemplate()
    {
        //returns the current template
        $query = "SELECT template FROM homepage_config WHERE cid = '" . $this->cid . "'";
        return $this->db->getOne($query);
    }

    /**
     * Gets the column widths for a particular homepage
     *
     * @return array|bool
     */
    public function getColumnWidths()
    {
        $query = "SELECT left_column_width, right_column_width
				  FROM homepage_config
				  WHERE cid = '{$this->cid}'";

        // Defaults: 70/30
        $result = $this->db->getRow($query);
        if (!isset($result['left_column_width'])) {
            $result['left_column_width'] = 70;
        }
        if (!isset($result['right_column_width'])) {
            $result['right_column_width'] = 30;
        }
        return $result;
    }

    /**
     * Update column widths
     *
     * @param  $left
     * @param  $right
     * @return void
     */
    public function updateColumnWidths($left, $right)
    {

        $columnWidths = [
            'cid'                => $this->cid,
            'left_column_width'  => $left,
            'right_column_width' => $right,
        ];

        $this->db->update('homepage_config', 'cid', $columnWidths);
    }

    /**
     * Updates left and right columns widths based on the proportion (out of 12)
     * the left column takes up.
     *
     * @param int $leftProportion min: 3, max: 9
     */
    public function updateGrid($leftProportion)
    {
        $leftProportion = min(9, max(3, $leftProportion));
        $leftPercent    = round($leftProportion * (100 / 12)) - .5;
        $this->updateColumnWidths($leftPercent, 99 - $leftPercent);
    }

    public function getColorList()
    {
        $query      = "SELECT left_head_font, left_head_bg, left_font, left_bg_primary,
						right_head_font, right_head_bg, right_font, right_bg_primary,
						top_head_font, top_head_bg, top_font, top_bg_primary
						FROM homepage_config WHERE cid = '{$this->cid}'";
        $colourList = $this->db->getRow($query);

        return $colourList;
    }

    /**
     * Removes componentry from homepage and resets to the template that was used to create it.
     * Also soft-deletes homepage content where applicable
     *
     * @return void
     */
    public function emptyHomepage()
    {
        /** @var $homepageCleanables \Schoolbox\IHomepageCleanable[] */
        $homepageCleanables = [
            new Assess_Main(),
            new Chat(),
            new Forum(),
            new Poll(),
            new Survey_Main(),
            new TextBox(),
            new Wiki(),

            Schoolbox\Calendar\EventMapper::getInstance(),
            Resource\File\Mapper::getInstance(),
            Schoolbox\Dropbox\Mapper::getInstance(),
            \SocialStream\PostMapper::getInstance(),
            Schoolbox\Comms\News\Audience\Mapper::getInstance(),
            Schoolbox\Blog\Homepage\Post\Mapper::getInstance(),
        ];

        foreach ($homepageCleanables as $homepageCleanable) {
            $homepageCleanable->homepageClean($this->cid);
        }

        // reset the homepage
        $categoryMapper = Resource\Folder\Mapper::getInstance();

        try {
            $category = $categoryMapper->findById($this->cid);
            $this->resetHomepageToTemplate($category->getHomepage()->getTemplate()->getId());
        } catch (\Exception $e) {
        }
    }

    /**
     * Resets a homepage to a template, or destroys the homepage if it is to be removed.
     * Returns the ID of the template used, or 0 if none.
     *
     * @param $categoryID
     * @param $templateID
     * @return int
     */
    public function resetHomepageToTemplate($templateID)
    {
        $categoryID = $this->cid;

        if ($templateID != Homepage::KEEP_EXISTING_HOMEPAGE) {
// if 'remove homepage', delete config and components, then set the templateID to zero
            if ($templateID == Homepage::REMOVE_HOMEPAGE_TEMPLATE_ID) {
                $templateID = $this->removeAllComponentsFromHomepage($categoryID);
            }

            // now continue as normal
            if ($templateID > 0) {
                $this->createHomepageFromTemplate($templateID);
            } else {
                // we don't want an invalid template ID going into the database
                $templateID = 0;
            }

            // deal with the template
            $this->db->update('homepage_config', 'cid', ['cid' => $categoryID, 'template' => $templateID]);
        }

        return $templateID;
    }


    /**
     * This simply copies a bunch of rows, changing 'homepage_template' to 'homepage'...quite simple, really.
     *
     * @param $categoryID
     * @param $templateID
     */
    public function createHomepageFromTemplate($templateID)
    {
        $categoryID = $this->cid;

        // locate all the component instances associated with this homepage (if they exist), and clear them out
        $cim        = Component_InstanceMapper::getInstance();
        $collection = $cim->findByColumnList(['module' => 'homepage', 'identifier' => $categoryID]);
        $cim->delete($collection);

        $categoryID = $this->db->quote($categoryID);
        $templateID = $this->db->quote($templateID);

        $sql = "
            SELECT id AS template, left_column_width, right_column_width
            FROM homepageTemplate
            WHERE id = $templateID
        ";

        $x[] = [$sql];

        // retrieve template data
        $tpl = $this->db->getRow($sql);

        // insert homepage instance into homepage_config
        $homepageConfig = [
            'cid'                => $categoryID,
            'template'           => $templateID,
            'left_column_width'  => $tpl['left_column_width'],
            'right_column_width' => $tpl['right_column_width'],
        ];

        foreach (['top', 'left', 'right'] as $position) {
            $homepageConfig = array_merge($homepageConfig, [
                $position . '_head_font'  => Config::get('homepage_colour_head_font'),
                $position . '_head_bg'    => Config::get('homepage_colour_head_bg'),
                $position . '_font'       => Config::get('homepage_colour_font'),
                $position . '_bg_primary' => Config::get('homepage_colour_bg_primary'),
            ]);
        }

        // remove (if exists) and replace the homepage config with the new template details
        $this->db->delete('homepage_config', 'cid', $categoryID);
        $this->db->insert('homepage_config', $homepageConfig);

        // retrieve, rearrange and reinsert the componentry
        $cim       = Component_InstanceMapper::getInstance();
        $ccm       = Component_ConfigMapper::getInstance();
        $instances = $cim->findByContext('homepage_template', $templateID);

        if (count($instances)) {
            foreach ($instances as $inst) {
                /* @var $inst Component_Instance */
                // preserve the config
                $configs = $inst->getConfig();

                $inst
                    ->setId(null)
                    ->setConfig(new \Schoolbox\Collection())
                    ->setModule('homepage')
                    ->setIdentifier($categoryID);

                $ids = $cim->save($inst);
                $id  = $ids[0];

                if (count($configs)) {
                    foreach ($configs as $c) {
                        /* @var $c Component_Config */
                        if ($c->getId()) {
                            $conf = new Component_Config();
                            $conf
                                ->setInstanceId($id)
                                ->setValue($c->getValue())
                                ->setConfigDefinitionId($c->getId());
                            $ccm->save($conf);
                        }
                    }
                }
            }
        }
    }

    /**
     * Delete all componentry from a homepage
     *
     * @return int
     */
    private function removeAllComponentsFromHomepage()
    {
        $categoryID = $this->db->quote($this->cid);

        // locate all the component instances associated with this homepage, and clear them out
        $cim        = Component_InstanceMapper::getInstance();
        $collection = $cim->findByColumnList(['module' => 'homepage', 'identifier' => $categoryID]);
        $cim->delete($collection);

        return 0;
    }

    public function saveDisplayProperties($listColor)
    {
        $query = "UPDATE homepage_config SET ";
        foreach ($listColor as $key => $color) {
            $query .= "`" . $this->db->quote($key) . "` = '" . $this->db->quote($color) . "', ";
        }
        $query = substr($query, 0, -2);
        $query .= " WHERE cid = '" . $this->cid . "'";
        return $this->db->query($query);

    }

    public function getConfig($categoryId = null)
    {
        if (!is_numeric($categoryId)) {
            $categoryId = $this->cid;
        }

        $query = "
            SELECT `cid`, `template`, `left_column_width`, `right_column_width`, `left_head_font`, `left_head_bg`,
                `left_font`, `left_bg_primary`, `right_head_font`,
                `right_head_bg`, `right_font`, `right_bg_primary`,
                `top_head_font`, `top_head_bg`, `top_font`, `top_bg_primary`
            FROM homepage_config
            WHERE `cid` = $categoryId
        ";

        $config = $this->db->getRow($query);

        return $config;
    }

    public function setConfig($config, $categoryId = null)
    {
        if (!is_numeric($categoryId)) {
            $categoryId = $this->cid;
        }

        if ($cleanConfig = $this->getConfig($categoryId)) {
            $this->db->update('homepage_config', 'cid', $config);
        } else {
            $this->db->insert('homepage_config', $config);
        }
    }
}
