<?php

/**
 * Homepage Config mapper, to create HomepageConfig objects
 * It contains things such as which template was initially used to create the
 * homepage, the column widths and colours
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Resource;

use Component_Config;
use Component_ConfigMapper;
use Component_Instance;
use Component_InstanceMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

/**
 * Class HomepageConfigMapper
 *
 * @package Schoolbox\Resource
 * @method HomepageConfig createModel($idValue = null)
 * @method HomepageConfig hydrate(array $row, $alias = '', $callback = null)
 * @method HomepageConfig findById($identifierValue)
 */
class HomepageConfigMapper extends Mapper
{
    protected function mapping()
    {
        $this->hasModel(HomepageConfig::class);
        $this->hasTable('homepage_config');

        $this->joins('cid', FolderMapper::class, 'id', null, self::COLUMN_IDENTIFIER, 'category', 'category');
        $this->joins('template', HomepageTemplateMapper::class, 'id', null, null, 'template');

        /**
         * Column widths
         */
        $this->uses('left_column_width', self::TYPE_DECIMAL);
        $this->uses('right_column_width', self::TYPE_DECIMAL);

        /**
         * Left Colours
         */
        $this->uses('left_head_font', self::TYPE_STRING);
        $this->uses('left_head_bg', self::TYPE_STRING);
        $this->uses('left_font', self::TYPE_STRING);
        $this->uses('left_bg_primary', self::TYPE_STRING);

        /**
         * Right Colours
         */
        $this->uses('right_head_font', self::TYPE_STRING);
        $this->uses('right_head_bg', self::TYPE_STRING);
        $this->uses('right_font', self::TYPE_STRING);
        $this->uses('right_bg_primary', self::TYPE_STRING);

        /**
         * Top colours
         */
        $this->uses('top_head_font', self::TYPE_STRING);
        $this->uses('top_head_bg', self::TYPE_STRING);
        $this->uses('top_font', self::TYPE_STRING);
        $this->uses('top_bg_primary', self::TYPE_STRING);

        $this->registerPersistSearchIndexCallbacks();
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

        /* Assign column widths from template preset;
         * I'm inferring that resetting to default also means column widths are reset.
         *
         *...but this seems like a really arcane way to do it?
         */
        $this->setModelProperty($homepage, $this->toCamelcase('left_column_width'), $homepage->getTemplate()->getLeftColumnWidth());
        $this->setModelProperty($homepage, $this->toCamelcase('right_column_width'), $homepage->getTemplate()->getLeftColumnWidth());

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

        foreach ($instances as $currentInstance) {
            // preserve the config
            /** @var \Component_ConfigDefinition[] $configs */
            $configs = $currentInstance->getConfig();

            /* Clone before saving, otherwise it does some magical caching stuff
             * and tries to re-assign config instances to the wrong component instance
             * in findByFields:247 (it tries to match the homepage template's configs
             * to the $inst, which is a misbehaviour that is seemingly rectified by clone)
             */
            $inst = clone($currentInstance);

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
                    try {
                        $src = $currentInstance->getConfigByKey('src');
                    } catch (\DomainException $e) {
                        $src = null;
                    }
                    if ($c->getKey() === 'layoutId' && (!$src || empty($src->getValue()))) {
                        $layoutMapper = \Schoolbox\Tile\Layout\Mapper::getInstance();
                        $tileMapper   = \Schoolbox\Tile\Mapper::getInstance();
                        try {
                            $layout = $layoutMapper->findById($c->getValue());
                            $layout
                                ->setId(null)
                                ->setDashboardRole(null)
                                ->setHomepageTemplate(null)
                                ->setFolder($homepage->getCategory());
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
