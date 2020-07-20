<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Textbox;

use Alaress\Web\Controller\Base;
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
     * Constructor
     *
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
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
                              ->setBody($data->body);
        $this->mapper->save($model);

        // TODO attach to component

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
