<?php
/**
 * Homepage Config mapper, to create HomepageConfig objects
 * It contains things such as which template was initially used to create the
 * homepage, the column widths and colours
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource;

use Component_Config;
use Component_ConfigMapper;
use Component_Instance;
use Component_InstanceMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper;

class HomepageConfigMapper extends Mapper
{
    protected function mapping()
    {
        $this->hasModel('\\Schoolbox\\Resource\\HomepageConfig');
        $this->hasTable('homepage_config');
        $this->hasMap(
            array(
                'column'     => 'cid',
                'property'   => 'category',
                'type'       => self::TYPE_MODEL,
                'join'       => 'category',
                'identifier' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'template',
                'property' => 'template',
                'type'     => self::TYPE_MODEL,
                'join'     => 'template',
            )
        );
        /**
         * Column widths
         */
        $this->hasMap(
            array(
                'column'   => 'left_column_width',
                'property' => 'leftColumnWidth',
                'type'     => self::TYPE_DECIMAL,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'right_column_width',
                'property' => 'rightColumnWidth',
                'type'     => self::TYPE_DECIMAL,
            )
        );
        /**
         * Left Colours
         */
        $this->hasMap(
            array(
                'column'   => 'left_head_font',
                'property' => 'leftHeadFont',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'left_head_bg',
                'property' => 'leftHeadBg',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'left_font',
                'property' => 'leftFont',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'left_bg_primary',
                'property' => 'leftBgPrimary',
                'type'     => self::TYPE_STRING,
            )
        );
        /**
         * Right Colours
         */
        $this->hasMap(
            array(
                'column'   => 'right_head_font',
                'property' => 'rightHeadFont',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'right_head_bg',
                'property' => 'rightHeadBg',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'right_font',
                'property' => 'rightFont',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'right_bg_primary',
                'property' => 'rightBgPrimary',
                'type'     => self::TYPE_STRING,
            )
        );
        /**
         * Top colours
         */
        $this->hasMap(
            array(
                'column'   => 'top_head_font',
                'property' => 'topHeadFont',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'top_head_bg',
                'property' => 'topHeadBg',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'top_font',
                'property' => 'topFont',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'top_bg_primary',
                'property' => 'topBgPrimary',
                'type'     => self::TYPE_STRING,
            )
        );
        /**
         * Joins
         */
        $this->hasJoin(
            'category',
            array(
                'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
                'this_key'  => 'category',
                'other_key' => 'id',
            )
        );
        $this->hasJoin(
            'template',
            array(
                'mapper'    => '\\Schoolbox\\Resource\\HomepageTemplateMapper',
                'this_key'  => 'template',
                'other_key' => 'id',
            )
        );
    }

    /**
     * More or less a convenience method for ComponentMapper::delete()
     *
     * @param HomepageConfig $homepage
     *
     * @return int the number of components deleted
     */
    public function emptyHomepage($homepage)
    {
        $cim        = Component_InstanceMapper::getInstance();
        $collection = $cim->findByColumnList(
            array('module' => 'homepage', 'identifier' => $homepage->getCategory()->getId())
        );
        $cim->delete($collection);
        return count($collection);
    }

    /**
     * Deletes all components from a homepage, then rebuilds from template
     *
     * @param HomepageConfig $homepage
     */
    public function resetToTemplate($homepage)
    {
        // kick all the components out
        $this->emptyHomepage($homepage);
        $homepageConfig = array();
        $positions      = array('top', 'left', 'right');
        $opts           = array('head_font', 'head_bg', 'font', 'bg_primary');
        // prep colour resets
        foreach ($positions as $position) {
            foreach ($opts as $opt) {
                $homepageConfig[$position . '_' . $opt] = $this->getConfigLoader()->get('homepage_colour_' . $opt);
            }
        }
        // set colour resets
        foreach ($homepageConfig as $field => $colour) {
            $this->setModelProperty($homepage, $this->toCamelcase($field), $colour);
        }
        // save
        $this->save($homepage);
        // no template, no homepage; don't try to add components
        if (is_null($homepage->getTemplate()) || $homepage->getTemplate()->getId() == 0) {
            return;
        }
        // add new components
        // retrieve, rearrange and reinsert the componentry
        $cim = Component_InstanceMapper::getInstance();
        $ccm = Component_ConfigMapper::getInstance();
        /** @var Component_Instance[] $instances */
        $instances = $cim->findByContext('homepage_template', $homepage->getTemplate()->getId());
        if (!count($instances)) {
            return;
        }
        foreach ($instances as $inst) {
            // preserve the config
            /** @var Component_Config[] $configs */
            $configs = $inst->getConfig();
            $inst
                ->setId(null)
                ->setConfig(new Collection())
                ->setModule('homepage')
                ->setIdentifier($homepage->getCategory()->getId());
            $ids = $cim->save($inst);
            if (!count($configs)) {
                continue;
            }
            $id = $ids[0];
            foreach ($configs as $c) {
                if ($c->getId()) {
                    // Gross special case for tile component:
                    // copy the homepage template's tile layout
                    // TODO move this somewhere it actually belongs
                    $value = $c->getValue();
                    if ($c->getKey() === 'layoutId') {
                        $layoutMapper = \Schoolbox\Tile\Layout\Mapper::getInstance();
                        $tileMapper   = \Schoolbox\Tile\Mapper::getInstance();
                        try {
                            $layout = $layoutMapper->findById($c->getValue());
                            $layout->setId(null);
                            $layoutMapper->save($layout);
                            $value = $layout->getId();
                            foreach ($layout->getTileList() as $tile) {
                                $tile->setId(null)->setTileLayout($layout);
                            }
                            $tileMapper->save($layout->getTileList());
                        } catch (Mapper\Exception $e) {
                            // Don't save anything
                            continue;
                        }
                    }
                    $conf = new Component_Config();
                    $conf
                        ->setInstanceId($id)
                        ->setValue($value)
                        ->setConfigDefinitionId($c->getId());
                    $ccm->save($conf);
                }
            }
        }
    }
}
