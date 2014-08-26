<?php
/**
 * Controller for editing the tile component
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Tile;

use Alaress\Web\Controller\Base;
use Component_Config;
use Component_ConfigMapper;
use Component_Instance;
use Schoolbox\Mapper\Exception as MapperException;

class Controller extends Base
{
    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * @var Layout\Mapper
     */
    protected $layoutMapper;

    /**
     * @var Component_ConfigMapper
     */
    protected $componentConfigMapper;

    /**
     * Displays a form for editing a tile layout.
     *
     * @param Component_Instance $component
     */
    public function layout(Component_Instance $component)
    {
        // Load layout
        $this->getResponse()->content->set(
            array(
                'title'    => 'Tile Component Layout',
                'template' => '@tile/layout.twig',
                'data'     => array(
                    'item'      => $this->getLayoutFromComponent($component),
                    'component' => $component,
                ),
            )
        );
    }

    /**
     * Save form for tile layout.
     *
     * @param Component_Instance $component
     */
    public function layoutForm(Component_Instance $component)
    {
        $layout = $this->getLayoutFromComponent($component);
        $layout
            ->setLayoutType($this->getRequest()->post['layout_type'])
            ->setGridWidth($this->getRequest()->post['grid_width'])
            ->setColumnCount($this->getRequest()->post['column_count'])
            ->setAspectRatioX($this->getRequest()->post['aspect_ratio_x'])
            ->setAspectRatioY($this->getRequest()->post['aspect_ratio_y'])
        ;
        $this->getLayoutMapper()->save($layout);

        // Update the component's configuration too.
        // (Should this be done in postSave?)
        $configMapper = $this->getComponentConfigMapper();
        foreach ($component->getConfig() as $configDefinition) {
            if ($configDefinition->getKey() == 'layout') {
                /** @var Component_Config $confI */
                $config = $configMapper->findByColumnList(
                    array(
                        'component_config_definition_id' => $configDefinition->getId(),
                        'component_instance_id'          => $component->getId()
                    )
                )->first();
                if (!($config instanceof Component_Config)) {
                    $config = new Component_Config();
                    $config->setConfigDefinitionId($configDefinition->getId());
                    $config->setInstanceId($component->getId());
                }
                $config->setValue($layout->getId());
                $configMapper->save($config);
                break;
            }
        }

        // and redirect back to the homepage
        $this->getResponse()->redirect->afterPost(
            '/homepage/'.$component->getIdentifier()
        );
    }

    /**
     * Retrieves the model layout object associated with this component, or an
     * empty one if none is associated.
     *
     * @param  Component_Instance $component
     * @return Layout\Model
     */
    protected function getLayoutFromComponent(Component_Instance $component)
    {
        $config   = $component->getConfigArray();
        $layoutId = $config['layout']['value'];
        try {
            return $this->getLayoutMapper()->findById(
                $layoutId,
                array('joins' => array('tile'))
            );
        } catch (MapperException $e) {
            return $this->getLayoutMapper()->createModel();
        }
    }

    /**
     * @param  Mapper $mapper
     * @return $this
     */
    public function setMapper($mapper)
    {
        $this->mapper = $mapper;
        return $this;
    }

    /**
     * @return Mapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }

    /**
     * @param  Layout\Mapper $layoutMapper
     * @return $this
     */
    public function setLayoutMapper($layoutMapper)
    {
        $this->layoutMapper = $layoutMapper;
        return $this;
    }

    /**
     * @return Layout\Mapper
     */
    public function getLayoutMapper()
    {
        return $this->layoutMapper;
    }

    /**
     * @param  Component_ConfigMapper $componentConfigMapper
     * @return $this
     */
    public function setComponentConfigMapper($componentConfigMapper)
    {
        $this->componentConfigMapper = $componentConfigMapper;
        return $this;
    }

    /**
     * @return Component_ConfigMapper
     */
    public function getComponentConfigMapper()
    {
        return $this->componentConfigMapper;
    }
}
