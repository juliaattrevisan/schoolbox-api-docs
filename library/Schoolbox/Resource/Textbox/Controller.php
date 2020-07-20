<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Textbox;

use Alaress\Web\Controller\Base;
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
}
