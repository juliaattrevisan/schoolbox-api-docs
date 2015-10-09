<?php

/* This script controls homepage layout functions.  It is used for setting up templates and customising homepage */

use Schoolbox\Resource;

class Homepage
{

    /* Used to remove homepage components and reset template
	 */
    const REMOVE_HOMEPAGE_TEMPLATE_ID = -1;
    const KEEP_EXISTING_HOMEPAGE = 0;

    function Homepage($cid)
    {
        $this->db = DeprecatedDatabase::getInstance();
        $this->cid = $cid;
    }

    function getTemplate()
    {
            //returns the current template
            $query = "SELECT template FROM homepage_config WHERE cid = '".$this->cid."'";
            return $this->db->getOne($query);
    }

    /**
     * @deprecated
     * @param null $type
     * @param null $categoryId
     * @return array|bool
     */
    function getComponentList($type = null, $categoryId = null)
    {
        if (!is_numeric($categoryId)) {
            $categoryId = $this->cid;
        }

        $query = "SELECT t.homepageComponentCategoryID AS categoryID, t.name as categoryName, id, c.name, component, description, allowMultiple,
                    (SELECT COUNT(*) FROM homepage_comp_location hcl WHERE hcl.component = c.id AND hcl.cid = {$categoryId}) as componentCount
                    FROM homepage_comp c
                    JOIN homepageComponentCategory t ON t.homepageComponentCategoryID = homepageCategoryID
                    LEFT JOIN homepage_comp_location l ON l.component = c.id AND cid = {$categoryId}

                  WHERE ( invisible = 'false' OR l.component IS NOT NULL )";


        if (is_numeric($type)) {
            $query .= " AND component = $type";
        }

        $query .= " GROUP BY id ORDER BY homepageCategoryID, c.name";

        return $this->db->getAll($query);
    }

    /**
     * @deprecated
     * @param null $categoryId
     * @return array|bool
     */
    public function getSimpleComponentList ($categoryId = null)
    {
        if (!is_numeric($categoryId)) {
            $categoryId = $this->cid;
        }

        $query = "
            SELECT `homepageComponentLocationID`, `cid`, `position`, `column`, `component`, `config`
            FROM homepage_comp_location
            WHERE cid = $categoryId
        ";

        $componentList = $this->db->getAll($query);

        return $componentList;
    }

    /**
     * @deprecated
     * @param $componentList
     * @param null $categoryId
     */
    public function setComponentList ($componentList, $categoryId = null)
    {
        if (!is_numeric($categoryId)) {
            $categoryId = $this->cid;
        }

        $this->deleteComponentPositions($categoryId);

        if (is_array($componentList)) {
            foreach ($componentList as $component) {
                $this->db->insert('homepage_comp_location', $component);
            }
        }
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

        $columnWidths = array('cid' => $this->cid,
                              'left_column_width' => $left,
                              'right_column_width' => $right,);

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
        $leftPercent    = round($leftProportion * (100/12)) - .5;
        $this->updateColumnWidths($leftPercent, 99 - $leftPercent);
    }

    function getColorList()
    {
        $query = "SELECT left_head_font, left_head_bg, left_font, left_bg_primary, left_bg_secondary, left_link, left_hover,
						right_head_font, right_head_bg, right_font, right_bg_primary, right_bg_secondary, right_link, right_hover,
						top_head_font, top_head_bg, top_font, top_bg_primary, top_bg_secondary, top_link, top_hover
						FROM homepage_config WHERE cid = '{$this->cid}'";
        $colourList = $this->db->getRow($query);

        // use defaults for missing colours
        $defaultColours = unserialize(HOMEPAGE_colors);
        if (is_array($colourList)) {
            foreach ($colourList as $key => $value) {
                if (empty($value)) {
                    $colourList[$key] = $defaultColours[$key];
                }
            }
        }
        return $colourList;
    }


    function savePageTemplate($template)
    {
        $query = "SELECT cid FROM homepage_config WHERE cid = '".$this->cid."'";
        $this->db->query($query);
        if ($row = $this->db->getNextArray()) {
            $query = "UPDATE homepage_config SET template = '$template' WHERE cid = '".$this->cid."'";
        } else {
            $query = "INSERT INTO homepage_config (cid, template) VALUES ('$this->cid', '$template')";
        }
        $this->db->query($query);
    }


    function deleteHomepage()
    {
        $groupHandler = new Group;
        $groupHandler->disbandGroup($this->cid);

        $this->emptyHomepage();

        $this->db->delete('homepage_config', 'cid', $this->cid);
        $this->deleteComponentPositions();
    }


    /**
    * Removes componentry from homepage and resets to the template that was used to create it.
     * Also soft-deletes homepage content where applicable
    *
    * @return void
    */
    public function emptyHomepage()
    {
        // TODO for 13.0.0: refactor this to call iterate across Component_Homepage_*Controllers instead, and call deleteData() if available
        //      ^ well...that clearly didn't happen: we're up to 15.0.0 now...

        // soft delete the data using handlers
        $allHandlers = array(
            'Assess_Main', 'Blog', 'Calendar', 'CategoryNews', 'Chat', 'Dropbox', 'Forum',
            'Poll', 'Submission', 'Survey_Main', 'TextBox', 'Wiki'
        );

        if (is_array($allHandlers) && count($allHandlers)) {
            foreach ($allHandlers as $currentComponent) {
                $handler = new $currentComponent();
                $handler->homepageClean($this->cid);
            }
        }


        // soft delete the data using mappers
        $mappers = [
            [ Resource\File\Mapper::getInstance(), 'category' ]
        ];

        foreach ($mappers as $array) {
            /** @var Schoolbox\Mapper $mapper */
            list ($mapper, $folderField) = $array;
            $mapper->deleteByColumn($folderField, $this->cid);
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
            $this->db->update('homepage_config', 'cid', array('cid' => $categoryID, 'template' => $templateID));
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
        $cim = Component_InstanceMapper::getInstance();
        $collection = $cim->findByColumnList(array('module' => 'homepage', 'identifier' => $categoryID));
        $cim->delete($collection);

        $categoryID = $this->db->quote($categoryID);
        $templateID = $this->db->quote($templateID);

        $sql = "
            SELECT id AS template, left_column_width, right_column_width
            FROM homepageTemplate
            WHERE id = $templateID
        ";

        $x[] = array($sql);

        // retrieve template data
        $tpl = $this->db->getRow($sql);

        // insert homepage instance into homepage_config
        $homepageConfig = array(
            'cid' => $categoryID,
            'template' => $templateID,
            'left_column_width' => $tpl['left_column_width'],
            'right_column_width' => $tpl['right_column_width'],
        );

        foreach (array('top','left','right') as $position) {
            $homepageConfig = array_merge($homepageConfig, array(
                $position . '_head_font' => Cfg::get('homepage_colour_head_font'),
                $position . '_head_bg' => Cfg::get('homepage_colour_head_bg'),
                $position . '_font' => Cfg::get('homepage_colour_font'),
                $position . '_bg_primary' => Cfg::get('homepage_colour_bg_primary'),
                $position . '_bg_secondary' => Cfg::get('homepage_colour_bg_secondary'),
                $position . '_link' => Cfg::get('homepage_colour_link'),
                $position . '_hover' => Cfg::get('homepage_colour_hover')
            ));
        }

        // remove (if exists) and replace the homepage config with the new template details
        $this->db->delete('homepage_config', 'cid', $categoryID);
        $this->db->insert('homepage_config', $homepageConfig);

        // retrieve, rearrange and reinsert the componentry
        $cim = Component_InstanceMapper::getInstance();
        $ccm = Component_ConfigMapper::getInstance();
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
                $id = $ids[0];

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
     * Can be used as a workaround for an absence of DB default field values,
     * but also allows for dynamic value duplication
     *
     * @param $table
     * @param array $map
     * @param string $sqlCondition
     * @param string $indexField
     * @return array
     */
    private function duplicateRowsOnTable($table, array $map, $sqlCondition = '', $indexField = 'id')
    {
        $queries = array();
        $sql = "SELECT * FROM $table $sqlCondition";
        $queries[] = $sql;

        $result = $this->db->query($sql);
        while ($r = mysqli_fetch_assoc($result)) {
            // hack at the data, then flick it back in
            unset($r[$indexField]);

            if (count($map)) {
                foreach ($map as $k => $v) {
                    $r[$k] = $v;
                }
            }
            $keys = implode('`, `', array_keys($r));
            $values = implode("', '", array_values($r));

            $q = "INSERT INTO `$table` (`$keys`) VALUES ('$values')";
            $queries[] = $q;

            $this->db->query($q);
        }

        return $queries;
    }


    /**
     * Delete all componentry from a homepage
     *
     * @return int
     */
    function removeAllComponentsFromHomepage()
    {
        $categoryID = $this->db->quote($this->cid);

        // locate all the component instances associated with this homepage, and clear them out
        $cim = Component_InstanceMapper::getInstance();
        $collection = $cim->findByColumnList(array('module' => 'homepage', 'identifier' => $categoryID));
        $cim->delete($collection);

        return 0;
    }

    /**
     * @deprecated
     * @param $component
     * @return int
     */
    function addComponent($component)
    {
        $query = "SELECT config FROM homepage_comp WHERE id = '$component'";
        $componentSetup = $this->db->getRow($query);

         /* We are inserting component at the top of the left column so we move all the existing components down */
        $query = "UPDATE homepage_comp_location SET position = position+1 WHERE cid = ".$this->cid." AND `column` = 1";
        $this->db->query($query);

        return $this->saveComponentPosition($component, 1, 1, $componentSetup['config']);

    }

    /**
     * @deprecated
     * @param null $categoryId
     */
    function deleteComponentPositions($categoryId = null)
    {
        if (!is_numeric($categoryId)) {
            $categoryId = $this->cid;
        }

        $this->db->delete('homepage_comp_location', 'cid', $categoryId);
    }

    /**
     * @deprecated
     * @param $component
     */
    function deleteComponent($component)
    {
        $query = "DELETE FROM homepage_comp_location WHERE cid = '$this->cid' AND homepageComponentLocationID = '$component'";
        $this->db->query($query);
    }


    /**
     * @deprecated
     * @param $component
     * @param $column
     * @param $position
     */
    function updateComponentPosition($component, $column, $position)
    {
        $query = array(
            "homepageComponentLocationID" => $component,
            "column" => $column,
            "position" => $position
        );

        $result = $this->db->update('homepage_comp_location', 'homepageComponentLocationID', $query);
    }

    /**
     * @deprecated
     * @param $component
     * @param $config
     */
    function updateComponentConfig($component, $config)
    {
        $query = array(
            "homepageComponentLocationID" => $component,
            "config" => $config
        );
        $this->db->update('homepage_comp_location', 'homepageComponentLocationID', $query);
    }

    /**
     * For updating available components
     *
     * @deprecated
     * @param $submitListComp
     * @param null $categoryId
     */
    function updateComponents($submitListComp, $categoryId = null)
    {
        if (!is_numeric($categoryId)) {
            $categoryId = $this->cid;
        }

        // Get components
        $query = "SELECT `cid`, `position`, `column`, `component`, `config`
			FROM homepage_comp_location
			WHERE cid = '{$categoryId}'
			ORDER BY `position`";
        $currentListComp = $this->db->getAll($query);
        $submitListComp = is_array($submitListComp) ? $submitListComp : array();
        if (is_array($currentListComp)) {
            // Remove deleted ones
            foreach ($currentListComp as $key => $comp) {
                if (!in_array($comp['component'], $submitListComp)) {
                    unset($currentListComp[$key]);
                } else {
                    unset($submitListComp[array_search($comp['component'], $submitListComp)]);
                }
            }
        }

        // Add new ones to end finding their
        $newListComp = $currentListComp;
        if (is_array($submitListComp)) {
            foreach ($submitListComp as $key => $comp) {
                $query = "SELECT size, config FROM homepage_comp WHERE id = $comp";
                $result = $this->db->getRow($query);
                $newComp = array(
                    "cid" => $categoryId,
                    "position" => "",
                    "column" => $result['size'],
                    "component" => $comp,
                    "config" => $result['config']
                );
                $newListComp[] = $newComp;
            }
        }
        // Reposition both columns and reorder array
        $count = array(1, 1);
        if (is_array($newListComp)) {
            foreach ($newListComp as $key => $newComp) {
                $newListComp[$key]['position'] = $count[$newComp['column'] - 1];
                $count[$newComp['column'] - 1]++;
            }
        }
        $newListComp = is_array($newListComp) ? $newListComp : array();
        $newListComp = array_values($newListComp);

        // Update
        $this->deleteComponentPositions();
        if (is_array($newListComp)) {
            foreach ($newListComp as $newComp) {
                $this->saveComponentPosition($newComp['component'], $newComp['column'], $newComp['position'], $newComp['config']);
            }
        }
    }


    /**
     * @deprecated
     * @param $component
     * @param $column
     * @param $position
     * @param $config
     * @return int
     */
    function saveComponentPosition($component, $column, $position, $config)
    {
        $query = array(
            "cid" => $this->cid,
            "component" => $component,
            "column" => $column,
            "position" => $position,
            "config" => $config
        );
        $this->db->insert('homepage_comp_location', $query);

        return $this->db->insertID();

    }

    /**
     * @deprecated
     * @param $homepageComponentLocationID
     * @param $config
     * @return bool
     */
    function setComponentConfig($homepageComponentLocationID, $config)
    {
        $result =  $this->db->update('homepage_comp_location', 'homepageComponentLocationID', array(
            'homepageComponentLocationID' => $homepageComponentLocationID,
            'config' => serialize($config)
        ));

        return $result;
    }

    /**
     * @deprecated
     * @param $homepageComponentLocationID
     * @return mixed
     */
    function getComponentConfig($homepageComponentLocationID)
    {
        $query = "SELECT config FROM homepage_comp_location WHERE homepageComponentLocationID = $homepageComponentLocationID";

        $config = unserialize($this->db->getOne($query));

        return $config;
    }

    function saveDisplayProperties($listColor)
    {
        $query = "UPDATE homepage_config SET ";
        foreach ($listColor as $key => $color) {
            $query .= "`".$this->db->quote($key)."` = '".$this->db->quote($color)."', ";
        }
        $query = substr($query, 0, -2);
        $query .= " WHERE cid = '".$this->cid."'";
        return $this->db->query($query);

    }

    public function getConfig ($categoryId = null)
    {
        if (!is_numeric($categoryId)) {
            $categoryId = $this->cid;
        }

        $query = "
            SELECT `cid`, `template`, `left_column_width`, `right_column_width`, `left_head_font`, `left_head_bg`,
                `left_font`, `left_bg_primary`, `left_bg_secondary`, `left_link`, `left_hover`, `right_head_font`,
                `right_head_bg`, `right_font`, `right_bg_primary`, `right_bg_secondary`, `right_link`, `right_hover`,
                `top_head_font`, `top_head_bg`, `top_font`, `top_bg_primary`, `top_bg_secondary`, `top_link`, `top_hover`
            FROM homepage_config
            WHERE `cid` = $categoryId
        ";

        $config = $this->db->getRow($query);

        return $config;
    }

    public function setConfig ($config, $categoryId = null)
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

    public function deleteConfig ($categoryId = null)
    {
        if (!is_numeric($categoryId)) {
            $categoryId = $this->cid;
        }

        $this->db->delete('homepage_config', 'cid', $categoryId);
    }
}
