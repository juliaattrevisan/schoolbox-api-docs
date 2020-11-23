<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge;

use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Web\Controller\Base;
use Schoolbox\API\Cursor\MapperCursor;
use Schoolbox\Learning\Badge\Translator;
use Schoolbox\Resource\Attachment\GenericZone;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

/**
 * Controller for badge requests.
 */
class Controller extends Base
{
    // How many badges show per page by default?
    private const BADGES_PER_PAGE = 40;


    /**
     * The badge mapper.
     */
    private Mapper $mapper;


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
     * Retrieve a list of badges.
     *
     * Depending on request type, this will fetch either a JSON or HTML result:
     * The HTML result will only contain a Vue component, the JSON will contain
     * the actual results.
     *
     * @api   GET /learning/badge/
     * @since 20.2
     */
    public function getBadges(): void
    {
        if ($this->isJsonRequest()) {
            $cursor  = new MapperCursor(
                $this->mapper,
                self::BADGES_PER_PAGE,
                [
                    'name' => $this->mapper::SORT_ORDER_ASC,
                    'id'   => $this->mapper::SORT_ORDER_ASC,
                ]
            );
            $results = $cursor->retrieveResults($this->request->query->get('cursor', null));
            $this->response->content->set(
                $this->buildJsonOutput([
                    'data'     => $results->map($this->buildModelToArrayTranslator()),
                    'metadata' => [
                        'count'  => $cursor->retrieveResultCount(),
                        'cursor' => [
                            'next'    => $cursor->next($results),
                            'current' => $cursor->current($results),
                        ],
                    ],
                ])
            );
            return;
        }

        // Output a Twig template
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningBadge/index.twig',
                [],
                'Badges'
            )
        );
    }


    /**
     * View the form for creating a badge.
     *
     * @api   GET /learning/badge/create/
     * @since 20.2
     */
    public function getCreateForm(): void
    {
        $attachzone = new GenericZone('image', Storage\Mapper::getInstance());
        $attachzone
            ->setAttachmentsLabel('')
            ->setAttachPrompt('upload a badge')
            ->setMimeFilter('image/*')
            ->setFileVar('image')
            ->setMulti(false)
            ->setThumbnailSize(64)
            ->setModalTitle('Select image');

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningBadge/createForm.twig',
                [
                    'attachzone' => $attachzone->getTwigContext(),
                    'categories' => Category\Mapper::getInstance()->findAll(),
                ],
                'Create Badge'
            )
        );
    }


    /**
     * Create a badge from POST data.
     *
     * @api   POST /learning/badge/
     * @since 20.2
     */
    public function postBadge(): void
    {
        $data       = json_decode(json_encode($this->request->content->get()), true);
        $translator = new Translator\ArrayToModel();
        $model      = $translator($data);

        $this->mapper->save($model);

        // Return the badge just created
        $this->response->content->set(
            $this->buildJsonOutput(
                $this->buildModelToArrayTranslator()($model)
            )
        );
    }


    /**
     * Builder for the model-to-array translator for badges.
     *
     * @return Translator\ModelToArray
     */
    private function buildModelToArrayTranslator(): Translator\ModelToArray
    {
        // TODO add links translator
        return new Translator\ModelToArray(
            new User\Translator\ModelToArray(),
            new Storage\Translator\ModelToArray(),
            new ScrubAdapter()
        );
    }
}
