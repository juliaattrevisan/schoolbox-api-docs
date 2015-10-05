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
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Storage;

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
     * @var Storage\Mapper
     */
    protected $storageMapper;

    /**
     * @var Folder\Mapper
     */
    protected $folderMapper;

    /**
     * Displays a form for editing a tile layout.
     *
     * @param Component_Instance $component
     */
    public function layout(Component_Instance $component)
    {
        // Load layout and context
        $folder = null;
        if ($component->getModule() === 'homepage') {
            $folder = $this->getFolderMapper()->findById($component->getIdentifier());
        }
        $this->getResponse()->content->set(
            array(
                'title'    => 'Tile Component Layout',
                'template' => '@tile/layout.twig',
                'data'     => array(
                    'item'      => $this->getLayoutFromComponent($component),
                    'component' => $component,
                    'folder'    => $folder,
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
        $code   = 200;
        $layout = $this->getLayoutFromComponent($component);
        $layout
            ->setGridWidth(intval($this->getRequest()->post['grid_width']))
            ->setGridPadding(intval($this->getRequest()->post['grid_padding']))
            ->setColumnCount(intval($this->getRequest()->post['column_count']))
            ->setColumnPadding(intval($this->getRequest()->post['column_padding']))
            ->setAspectRatioX(intval($this->getRequest()->post['aspect_ratio_x']))
            ->setAspectRatioY(intval($this->getRequest()->post['aspect_ratio_y']))
        ;
        switch ($this->getRequest()->post['layout_type']) {
            case 'grid':
                $layout->setLayoutType(Layout\Model::LAYOUT_TYPE_GRID);
                break;
            case 'column':
                $layout->setLayoutType(Layout\Model::LAYOUT_TYPE_COLUMN);
                break;
        }

        if ($this->getLayoutMapper()->save($layout)) {
            // Rearrange tiles
            $tilesOrder = $this->getRequest()->post['tile'];
            if (!empty($tilesOrder)) {
                foreach ($layout->getTileList() as $tile) {
                    $order = array_search($tile->getId(), $tilesOrder);
                    if ($order !== false) {
                        $tile->setOrder($order);
                    }
                }
                if (!$this->getMapper()->save($layout->getTileList())) {
                    $code = 500;
                }
            }
        } else {
            $code = 500;
        }

        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set(
                array(
                    'format' => 'json',
                    'data'   => $layout,
                )
            );
        } else {
            // redirect back to wherever
            switch ($component->getModule()) {
                case 'dashboard':
                    $href = '/adminv2/dashboard#'.$component->getIdentifier();
                    break;
                case 'homepage_template':
                    $href = '/adminv2/homepage#'.$component->getIdentifier();
                    break;
                case 'homepage':
                default:
                    $href = '/homepage/'.$component->getIdentifier();
                    break;
            }
            $this->getResponse()->redirect->afterPost($href);
        }


    }

    /**
     * Displays a form for adding a tile
     *
     * @param Component_Instance $component
     */
    public function add(Component_Instance $component)
    {
        $model = $this->getMapper()->createModel();
        $this->getResponse()->content->set(
            array(
                'title'    => 'Add Tile',
                'template' => '@tile/modify.twig',
                'data'     => array(
                    'item'      => $model,
                    'layout'    => $this->getLayoutFromComponent($component),
                    'component' => $component,
                    'attachzone' => $this->getAttachZoneData($model)
                ),
            )
        );
    }

    /**
     * Create form for a new tile.
     *
     * @param Component_Instance $component
     * @throws \RuntimeException if tile is added to non-existent layout
     */
    public function create(Component_Instance $component)
    {
        /** @var Model $item */
        $item = $this->getMapper()->createModel();
        $this->updateItemWithFormValues(
            $item,
            $this->getRequest()->post
        );

        // updateItemWithFormValues() doesn't set the layout item - set it here.
        $layout = $this->getLayoutFromComponent($component);
        if (!($layout->getId())) {
            throw new \RuntimeException('Layout must be created before adding a tile');
        }
        $item->setTileLayout($layout);

        $this->getMapper()->save($item);
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate(
                'tile.layout',
                array('componentId' => $component->getId())
            )
        );
    }

    /**
     * Form for editing a tile
     *
     * @param Model              $item
     * @param Component_Instance $component
     */
    public function edit(Model $item, Component_Instance $component)
    {
        $this->getResponse()->content->set(
            array(
                'title'    => 'Add Tile',
                'template' => '@tile/modify.twig',
                'data'     => array(
                    'item'      => $item,
                    'component' => $component,
                    'attachzone' => $this->getAttachZoneData($item)
                ),
            )
        );
    }

    /**
     * Update action for tiles
     *
     * @param Model              $item
     * @param Component_Instance $component
     */
    public function update(Model $item, Component_Instance $component)
    {
        $this->updateItemWithFormValues(
            $item,
            $this->getRequest()->post
        );

        $this->getMapper()->save($item);
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate(
                'tile.layout',
                array('componentId' => $component->getId())
            )
        );
    }

    /**
     * Delete action for tiles
     *
     * @param Model              $item
     * @param Component_Instance $component
     */
    public function delete(Model $item, Component_Instance $component)
    {
        $code = 200;
        if (!$this->getMapper()->delete($item)) {
            $code = 500;
        }
        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->status->setCode($code);
            $this->getResponse()->content->set(
                array(
                    'format' => 'json',
                    'data'   => array(),
                )
            );
        } else {
            $this->getResponse()->redirect->afterPost(
                $this->getRouter()->generate(
                    'tile.layout',
                    array('componentId' => $component->getId())
                )
            );
        }
    }


    /**
     * @param Model $model
     * @return array
     */
    protected function getAttachZoneData(Model $model)
    {
        $zone = new Zone('tile');
        $zone
            ->setThumbnailSize(64)
            ->setModalTitle('Select Image')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Set background image')
            ->setMimeFilter('image/*')
            ->setReadOnly(false)
            ->setMulti(false);

        if ($model->getBackgroundImage()) {
            $zone->attachFiles(array($model->getBackgroundImage()->getHash()));
        }

        return $zone->getTwigContext();
    }

    /**
     * Updates an item with values passed from the form.
     *
     * @param Model        $item  The tile to update
     * @param \ArrayObject $post  An array-like object that abstracts $_POST
     */
    protected function updateItemWithFormValues(Model $item, $post)
    {
        $item
            ->setTitleText($post['title_text'])
            ->setTitleColor($post['title_color'] ?: '00000000')
            ->setTitleBackgroundColor($post['title_background_color'] ?: '00000000')
            ->setTitlePosition($post['title_position'])
            ->setLink($post['link'])
            ->setLinkTarget($post['link_target'])
            ->setBackgroundColor($post['background_color'] ?: '00000000')
            ->setBackgroundImageContained(!empty($post['background_image_contained']))
            ->setBackgroundPosition($post['background_position'])
            ->setHoverColor($post['hover_color'] ?: '00000000');

        if (is_array($post['attachment'])) {
            try {
                $factory = new CollectorFactory();
                $image = $factory->build($post['attachment'])->getFirst();
                $item->setBackgroundImage($image);
            } catch (\Schoolbox\Resource\Exception $e) {
                error(43);
                exit;
            }
        } else {
            $item->setBackgroundImage(null);

        }
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
        $layoutId = $config['layoutId']['value'];
        $mapper   = $this->getLayoutMapper();
        try {
            return $mapper->findById(
                $layoutId,
                array('joins' => array('tile'))
            );
        } catch (MapperException $e) {
            $model = $mapper->createModel();
            $mapper->save($model);

            // Update the component's configuration.
            // (Should this be done in postSave?)
            $configMapper = $this->getComponentConfigMapper();
            foreach ($component->getConfig() as $configDefinition) {
                if ($configDefinition->getKey() == 'layoutId') {
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
                    $config->setValue($model->getId());
                    $configMapper->save($config);
                    break;
                }
            }
            return $model;
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

    /**
     * @param  Storage\Mapper $storageMapper
     * @return $this
     */
    public function setStorageMapper($storageMapper)
    {
        $this->storageMapper = $storageMapper;
        return $this;
    }

    /**
     * @return Storage\Mapper
     */
    public function getStorageMapper()
    {
        return $this->storageMapper;
    }

    /**
     * @return Folder\Mapper
     */
    public function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @param  Folder\Mapper $folderMapper
     *
     * @return $this
     */
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }
}
