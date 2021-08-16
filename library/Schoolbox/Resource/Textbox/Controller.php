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
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use Scrub;

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
     * @param Mapper                 $mapper
     * @param Component_ConfigMapper $componentConfigMapper
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
     * @param Folder\Model $folder
     */
    public function createTextboxInFolder(Folder\Model $folder): void
    {
        $model = $this->persistTextbox($this->mapper->createModel()->setCategory($folder));
        $this->mapper->save($model);
        $this->response->content->set($this->buildJsonOutput($model));
    }


    /**
     * Create a textbox within a folder, tied to a particular component within
     * that folder.
     *
     * @api   POST /textbox/folder/{folderId}/component/{componentId}
     * @since 20.1
     *
     * @param Folder\Model        $folder
     * @param Component_Instance $component
     */
    public function createTextboxInFolderAndComponent(Folder\Model $folder, Component_Instance $component): void
    {
        $model = $this->persistTextbox($this->mapper->createModel()->setCategory($folder));
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
        $textbox = $this->persistTextbox($textbox);
        $this->mapper->save($textbox);
        $this->response->content->set($this->buildJsonOutput($textbox));
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
        $this->mapper->delete($textbox);

        // Empty response
    }


    /**
     * Helper method for persisting textbox
     *
     * @param Model $model
     * @return Model
     */
    private function persistTextbox(Model $model): Model
    {
        /** @var \stdClass $data */
        $data = $this->request->content->get();
        return $model->setHeading($data->heading)
                     ->setBody($data->body)
                     ->setDeletedAt(null);
    }


    /**
     * Display the form for modifying textboxed in this folder.
     *
     * This no longer contains the textbox's usage: that is loaded separately in
     * `getUsageForFolder`.
     *
     * @api GET /textbox/folder/{folderId}
     * @param Folder\Model $folder
     */
    public function getIndexForFolder(Folder\Model $folder): void
    {
        $allBreadcrumbs = [
            [$folder->getNameWithCodes(), "/homepage/{$folder->getId()}"],
            ['Text Boxes', null]
        ];

        $this->response->content->set($this->buildHtmlOutput('@textbox/index.twig', [
            'folder'         => $folder,
            'allBreadcrumbs' => $allBreadcrumbs,
        ]));
    }


    /**
     * Output textbox usage for this folder, as a JSON response.
     *
     * @api GET /textbox/folder/{folderId}/usage
     * @since 21.1
     *
     * @param Folder\Model $folder
     */
    public function getUsageForFolder(Folder\Model $folder): void
    {
        // Convert `$usages` to an array structure
        $translateFolder = new Folder\Translator\ModelToArray\LinkOnly();

        $usages = [];
        foreach ($this->mapper->findUsage($folder->getId()) as $usage) {
            /** @var Model $textbox */
            $textbox = $usage['textbox'];

            /** @var GenericCollection|Folder\Model[] $folders */
            $folders  = $usage['folders'] ?? new GenericCollection(Folder\Model::class);
            $usages[] = [
                'dashboards' => array_values($usage['dashboards'] ?? []),
                'folders'    => $folders->map($translateFolder),
                'textbox'    => [
                    'id'       => $textbox->getId(),
                    'heading'  => $textbox->getHeading(),
                    'body'     => Scrub::html($textbox->getBody()),
                    'category' => $translateFolder($textbox->getCategory()),
                    '_links'   => [
                        'update' => $this->getRouteIfAuthorized(
                            'textbox.updateTextbox',
                            [
                                'textbox' => $textbox,
                                'folder'  => $textbox->getCategory(),
                                'id'      => $textbox->getId(),
                            ]
                        ),
                        'delete' => $this->getRouteIfAuthorized(
                            'textbox.deleteTextbox',
                            [
                                'textbox' => $textbox,
                                'folder'  => $textbox->getCategory(),
                                'id'      => $textbox->getId(),
                            ]
                        ),
                    ]
                ]
            ];
        }

        $this->response->content->set(
            $this->buildJsonOutput($usages)
        );
    }


    /**
     * Mass rename textboxes from a set of (textboxId, name) tuples
     *
     * @param Folder\Model $folder
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postRename(Folder\Model $folder): void
    {
        $tuples = $this->getRequest()->post['textbox'] ?? [];

        $textboxes = $this->mapper->findByColumn('textboxID', array_keys($tuples))->indexBy('id');

        /* Update heading for every textbox requested
         *
         * If someone else deletes a textbox in the meantime it's okay,
         * because findByColumn won't retrieve it despite it being in
         * the POST payload
         */
        foreach ($textboxes as $textbox) {
            $newHeading = trim((string)$this->getRequest()->post['textbox'][$textbox->getId()]);

            /* Only save headings if they had length (ignore empties)
             */
            if ('' !== $newHeading) {
                $textbox->setHeading($newHeading);

                $this->mapper->save($textbox);
            }
        }

        /* I guess you go back to the homepage instead of the Textbox Usage index...?
         */
        $this->getResponse()->redirect->to("/homepage/{$folder->getId()}");
    }
}
