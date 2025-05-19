<?php

/**
 * Controller for editing the tile component
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Tile;

use Alaress\Color\ColorFactory;
use Alaress\Web\Controller\Base;
use Component_Config;
use Component_ConfigMapper;
use Component_Instance;
use InvalidArgumentException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Folder;

class Controller extends Base
{
    /**
     * @var Mapper
     */
    private $mapper;

    /**
     * @var Layout\Mapper
     */
    private $layoutMapper;

    /**
     * @var Component_ConfigMapper
     */
    private $componentConfigMapper;

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;

    /**
     * @var ErrorMapper
     */
    private $errorMapper;


    /**
     * Constructor
     *
     * @param Mapper                 $mapper
     * @param Layout\Mapper          $layoutMapper
     * @param Component_ConfigMapper $componentConfigMapper
     * @param Folder\Mapper          $folderMapper
     * @param ErrorMapper            $errorMapper
     */
    public function __construct(
        Mapper $mapper,
        Layout\Mapper $layoutMapper,
        Component_ConfigMapper $componentConfigMapper,
        Folder\Mapper $folderMapper,
        ErrorMapper $errorMapper
    ) {
        $this->mapper                = $mapper;
        $this->layoutMapper          = $layoutMapper;
        $this->componentConfigMapper = $componentConfigMapper;
        $this->folderMapper          = $folderMapper;
        $this->errorMapper           = $errorMapper;
    }


    /**
     * Displays a form for editing a tile layout.
     *
     * @param Layout\Model $layout
     */
    public function layout(Layout\Model $layout)
    {
        // Load layout
        $this->response = $this->responseFromTemplate(
            '@tile/layout.twig',
            [
                'item' => $layout,
                'maxPaddingPercentage' => Layout\Model::MAX_PADDING_PERCENTAGE,
                'minAspectRatioXorY' => Layout\Model::MIN_ASPECT_RATIO_X_OR_Y,
                'maxAspectRatioXorY' => Layout\Model::MAX_ASPECT_RATIO_X_OR_Y
            ],
            'Tile Component Layout',
        );
    }

    /**
     * Save form for tile layout.
     *
     * @param Layout\Model $layout
     * @throws MapperException
     */
    public function layoutForm(Layout\Model $layout)
    {
        $code = 200;

        $columnPadding = (int)$this->requestPost['column_padding'];

        /* If column padding exceeds the maximum, set it to zero
         */
        if ($columnPadding > Layout\Model::MAX_PADDING_PERCENTAGE) {
            $columnPadding = 0;
        }

        if (
            $this->requestPost['aspect_ratio_x'] < Layout\Model::MIN_ASPECT_RATIO_X_OR_Y ||
            $this->requestPost['aspect_ratio_y'] > Layout\Model::MAX_ASPECT_RATIO_X_OR_Y ||
            $this->requestPost['aspect_ratio_x'] < Layout\Model::MIN_ASPECT_RATIO_X_OR_Y ||
            $this->requestPost['aspect_ratio_y'] > Layout\Model::MAX_ASPECT_RATIO_X_OR_Y
        ) {
            // Note client-side validation should prevent this from happening.
            throw new InvalidArgumentException(
                'Custom aspect ratio values must be in range ' .
                '[' . Layout\Model::MIN_ASPECT_RATIO_X_OR_Y . ', ' . Layout\Model::MAX_ASPECT_RATIO_X_OR_Y . '].'
            );
        }

        $layout
            ->setHeading($this->requestPost->get('heading', ''))
            ->setGridWidth((int)$this->requestPost['grid_width'])
            ->setGridPadding((int)$this->requestPost['grid_padding'])
            ->setColumnCount((int)$this->requestPost['column_count'])
            ->setColumnPadding($columnPadding)
            ->setAspectRatioX((int)$this->requestPost['aspect_ratio_x'])
            ->setAspectRatioY((int)$this->requestPost['aspect_ratio_y']);
        switch ($this->requestPost['layout_type']) {
            case 'grid':
                $layout->setLayoutType(Layout\Model::LAYOUT_TYPE_GRID);
                break;
            case 'column':
                $layout->setLayoutType(Layout\Model::LAYOUT_TYPE_COLUMN);
                break;
        }

        if ($this->layoutMapper->save($layout)) {
            // Rearrange tiles
            $tilesOrder = $this->requestPost->get('tile', []);
            if (!empty($tilesOrder)) {
                foreach ($layout->getTileList() as $tile) {
                    $order = array_search($tile->getId(), $tilesOrder);
                    if ($order !== false) {
                        $tile->setOrder($order);
                    }
                }
                if (!$this->mapper->save($layout->getTileList())) {
                    $code = 500;
                }
            }
        } else {
            $code = 500;
        }

        // XHR: return a simple JSON response
        if ($this->isXhrRequest()) {
            $this->response = $this->responseAsJson($layout)->withStatus($code);
            return;
        }

        // Otherwise: redirect back to a return URL if there is one
        $returnUrl = $this->requestQuery->get(Redirect::RETURN_URL);
        if (!empty($returnUrl)) {
            $href = Redirect::decode($returnUrl);
        } elseif ($layout->getHomepageTemplate()) {
            $href = '/adminv2/dashboard#' . $layout->getHomepageTemplate()->getId();
        } elseif ($layout->getDashboardRole()) {
            $href = '/adminv2/homepage#' . $layout->getDashboardRole()->getId();
        } elseif ($layout->getFolder()) {
            $href = '/homepage/' . $layout->getFolder()->getId();
        } else {
            // Should never happen
            $href = '#';
        }
        $this->response = $this->redirectAfterPost($href);
    }

    /**
     * Displays a form for adding a tile
     *
     * @param Layout\Model $layout
     * @throws MapperException
     */
    public function add(Layout\Model $layout)
    {
        /** @var Model $model */
        $model = $this->mapper->createModel();
        $this->response = $this->responseFromTemplate(
            '@tile/modify.twig',
            [
                'item'       => $model,
                'layout'     => $layout,
                'attachzone' => $this->getAttachZoneData($model),
            ],
            'Add Tile',
        );
    }

    /**
     * Displays a form for adding a tile to a component
     *
     * @param Component_Instance $component
     * @throws MapperException
     */
    public function addToComponent(Component_Instance $component)
    {
        // Does a layout exist for this component?
        // Yes: redirect to add($layout)
        // No:  Create a layout, and redirect to it
        try {
            $layout = $this->findLayoutForComponent($component);
            $this->response = $this->redirectFound(
                $this->routeGenerator->generate(
                    'tile.add',
                    ['layoutId' => $layout->getId()]
                ) . $this->buildReturnUrl()
            );
        } catch (MapperException $e) {
            $layout = $this->buildLayoutForComponent($component);
            $this->response = $this->redirectFound(
                $this->routeGenerator->generate(
                    'tile.layout',
                    ['layoutId' => $layout->getId()]
                ) . $this->buildReturnUrl()
            );
        }
    }

    /**
     * Create form for a new tile.
     *
     * @param Layout\Model $layout
     * @throws MapperException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function create(Layout\Model $layout)
    {
        /** @var Model $item */
        $item = $this->mapper->createModel();
        $this->updateItemWithFormValues($item, $this->requestPost);

        // updateItemWithFormValues() doesn't set the layout item - set it here.
        if (!($layout->getId())) {
            throw new \RuntimeException('Layout must be created before adding a tile');
        }
        $item->setTileLayout($layout);

        $this->mapper->save($item);
        $this->response = $this->redirectAfterPost(
            $this->routeGenerator->generate(
                'tile.layout',
                ['layoutId' => $layout->getId()]
            ) . $this->buildReturnUrl()
        );
    }

    /**
     * Form for editing a tile
     *
     * @param Model              $item
     */
    public function edit(Model $item)
    {
        $this->response = $this->responseFromTemplate(
            '@tile/modify.twig',
            [
                'item'       => $item,
                'layout'     => $item->getTileLayout(),
                'attachzone' => $this->getAttachZoneData($item),
            ],
            'Add Tile',
        );
    }

    /**
     * Update action for tiles
     *
     * @param Model $item
     */
    public function update(Model $item)
    {
        $this->updateItemWithFormValues(
            $item,
            $this->requestPost
        );

        $this->mapper->save($item);
        $this->response = $this->redirectAfterPost(
            $this->routeGenerator->generate(
                'tile.layout',
                ['layoutId' => $item->getTileLayout()->getId()]
            ) . $this->buildReturnUrl()
        );
    }

    /**
     * Delete action for tiles
     *
     * @param Model $item
     */
    public function delete(Model $item)
    {
        $code = 200;
        if (!$this->mapper->delete($item)) {
            $code = 500;
        }
        if ($this->isXhrRequest()) {
            $this->response = $this->responseAsJson([])->withStatus($code);
        } else {
            $this->response = $this->redirectAfterPost(
                $this->routeGenerator->generate(
                    'tile.layout',
                    ['layoutId' => $item->getTileLayout()->getId()]
                ) . $this->buildReturnUrl()
            );
        }
    }


    /**
     * @param Model $model
     * @return array
     */
    private function getAttachZoneData(Model $model)
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
            $zone->attachFiles([$model->getBackgroundImage()]);
        }

        return $zone->getTwigContext();
    }

    /**
     * Updates an item with values passed from the form.
     *
     * @param Model        $item The tile to update
     * @param \ArrayObject $post An array-like object that abstracts $_POST
     * @throws \Exception if the title text min/max size is missing, or the
     *                    background image can't be set
     */
    private function updateItemWithFormValues(Model $item, $post)
    {
        // Generic error if title text min/max size are not set
        if ($post['title_text_min_size'] === '' || $post['title_text_max_size'] === '') {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $colorFactory = new ColorFactory();
        $item
            ->setTitleText($post['title_text'])
            ->setTitleTextMinSize($post['title_text_min_size'])
            ->setTitleTextMaxSize($post['title_text_max_size'])
            ->setTitleColor($colorFactory->build($post['title_color'] ?: '00000000'))
            ->setTitleBackgroundColor($colorFactory->build($post['title_background_color'] ?: '00000000'))
            ->setTitlePosition($post['title_position'])
            ->setLink($post['link'])
            ->setLinkTarget($post['link_target'])
            ->setBackgroundColor($colorFactory->build($post['background_color'] ?: '00000000'))
            ->setBackgroundImageContained(!empty($post['background_image_contained']))
            ->setBackgroundPosition($post['background_position'])
            ->setHoverColor($colorFactory->build($post['hover_color'] ?: '00000000'));

        if (is_array($post['attachment'] ?? false)) {
            try {
                $factory = new CollectorFactory();
                $image   = $factory->build($post['attachment'])->getFirst();
                $item->setBackgroundImage($image);
            } catch (\Schoolbox\Resource\Exception $e) {
                throw $this->errorMapper->generateException(ErrorMapper::ERROR_FILE_UPLOAD_INCOMPLETE);
            }
        } else {
            $item->setBackgroundImage(null);
        }
    }

    /**
     * Retrieves the model layout object associated with this component, or an
     * empty one if none is associated.
     *
     * @param Component_Instance $component
     * @return Layout\Model
     * @throws MapperException if no component exists
     */
    private function findLayoutForComponent(Component_Instance $component)
    {
        $config   = $component->getConfigArray();
        $layoutId = $config['layoutId']['value'];
        return $this->layoutMapper->findById($layoutId);
    }


    /**
     * Build a layout object for the given component.
     *
     * @param Component_Instance $component
     * @return Layout\Model
     * @throws MapperException
     * @throws \DomainException if the `layoutId` config doesn't exist on
     *                          `$component` (this should not ever happen)
     */
    private function buildLayoutForComponent(Component_Instance $component): Layout\Model
    {
        $model = $this->layoutMapper->createForComponent($component);
        $this->layoutMapper->save($model);


        // Save the config setting for the layout.
        $layoutConfigDefinition = $component->getConfigByKey('layoutId');
        $config                 = $this->componentConfigMapper->findByColumnList([
            'component_config_definition_id' => $layoutConfigDefinition->getId(),
            'component_instance_id'          => $component->getId(),
        ])->first();
        if (!($config instanceof Component_Config)) {
            $config = new Component_Config();
            $config->setConfigDefinitionId($layoutConfigDefinition->getId());
            $config->setInstanceId($component->getId());
        }
        $config->setValue($model->getId());
        $this->componentConfigMapper->save($config);
        return $model;
    }


    /**
     * Builds the return URL out of what was passed via GET.
     *
     * @return string
     */
    private function buildReturnUrl(): string
    {
        $encodedReturnUrl = $this->requestQuery->get(Redirect::RETURN_URL);
        if (!isset($encodedReturnUrl)) {
            return '';
        }
        return '?' . Redirect::RETURN_URL . '=' . $encodedReturnUrl;
    }
}
