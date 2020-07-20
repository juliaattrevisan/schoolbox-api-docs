<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Textbox;

use Alaress\Web\Controller\Base;
use Component_ConfigMapper;
use Component_Instance;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Controller for textbox actions.
 */
class Controller extends Base
{
    /**
     * @var Mapper
     */
    private $mapper;


    /**
     * @var Component_ConfigMapper
     */
    private $componentConfigMapper;


    /**
     * Constructor
     *
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper, Component_ConfigMapper $componentConfigMapper)
    {
        $this->mapper                = $mapper;
        $this->componentConfigMapper = $componentConfigMapper;
    }


    /**
     * Create a textbox within a folder.
     *
     * @api   POST /textbox/folder/{folderId}
     * @since 20.1
     *
     * @param FolderModel $folder
     */
    public function createTextboxInFolder(FolderModel $folder): void
    {
        //
    }


    /**
     * Create a textbox within a folder, tied to a particular component within
     * that folder.
     *
     * @api   POST /textbox/folder/{folderId}/component/{componentId}
     * @since 20.1
     *
     * @param FolderModel        $folder
     * @param Component_Instance $component
     */
    public function createTextboxInFolderAndComponent(FolderModel $folder, Component_Instance $component): void
    {
        /** @var \stdClass $data */
        $data  = $this->request->content->get();
        $model = $this->mapper->createModel()
                              ->setCategory($folder)
                              ->setHeading($data->heading)
                              ->setBody($data->body)
                              ->setDeletedAt(null);
        $this->mapper->save($model);


        // Update the component's textbox
        $textboxConfigDefinition = $component->getConfigByKey('textbox');
        $textboxConfig           = $this->componentConfigMapper->findByColumnList([
            'component_config_definition_id' => $textboxConfigDefinition->getId(),
            'component_instance_id'          => $component->getId()
        ])->first();
        if (!$textboxConfig) {
            $textboxConfig = $this->componentConfigMapper->createModel()
                                                         ->setConfigDefinitionId($textboxConfigDefinition->getId())
                                                         ->setInstanceId($component->getId());
        }
        $textboxConfig->setValue($model->getId());
        $this->componentConfigMapper->save($textboxConfig);


        // Render the textbox
        $this->response->content->set($this->buildJsonOutput($model));
    }


    /**
     * Update a textbox.
     *
     * @api   PUT /textbox/{id}
     * @since 20.1
     *
     * @param Model $textbox
     */
    public function updateTextbox(Model $textbox): void
    {
        //
    }


    /**
     * Delete a textbox.
     *
     * @api   DELETE /textbox/{id}
     * @since 20.1
     *
     * @param Model $textbox
     */
    public function deleteTextbox(Model $textbox): void
    {
        //
    }
}
