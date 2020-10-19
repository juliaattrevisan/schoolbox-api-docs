<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Learning\Moment\Evidence\Translator;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\GenericZone;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Scrub;

/**
 * Controller for learning moments.
 */
class Controller extends Base
{
    private const MOMENT_TYPE_TEXT   = 'text';
    private const MOMENT_TYPE_UPLOAD = 'upload';


    /**
     * @var Mapper
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
     * Render a create form for a learning moment.
     *
     * @api   GET /learning/evidence/user/{userIds}/create
     * @since 20.2
     *
     * @param Collection|User[] $users
     */
    public function getCreateForm(Collection $users): void
    {
        // For ease of rendering: filter out users which are not myself
        $sharingForSelf = false;
        $otherUsers     = $users->emptyClone();
        $thisUserId     = $this->getUser()->getId();
        foreach ($users as $user) {
            if ($user->getId() === $thisUserId) {
                $sharingForSelf = true;
            } else {
                $otherUsers->add($user);
            }
        }

        // Which variant is being shown?
        // * text
        // * upload
        $momentType = $this->request->query->get('momentType');
        $template   = '@learningMomentEvidence/form/text.twig';
        $params     = [
            'users'          => $users,
            'userIds'        => $users->getPropertyValues('id'),
            'otherUsers'     => $otherUsers,
            'sharingForSelf' => $sharingForSelf,
        ];
        switch ($momentType) {
            case self::MOMENT_TYPE_UPLOAD:
                $template = '@learningMomentEvidence/form/upload.twig';

                // Also shovel in an attachzone
                $zone = new GenericZone('storage', \Schoolbox\Resource\Storage\Mapper::getInstance());
                $zone->setAttachmentsLabel('')
                     ->setAttachPrompt('upload files to this location')
                     ->setMimeFilter('*/*')
                     ->setFileVar('file')
                     ->setMulti(false)
                     ->setThumbnailSize(64)
                     ->setModalTitle('Select file');
                $params['zone'] = $zone->getTwigContext();
                break;

            case self::MOMENT_TYPE_TEXT:
            default:
                break;
        }

        $this->response->content->set(
            $this->buildHtmlOutput($template, $params)
        );
    }


//    /**
//     * Retrieve a JSON representation of a single learning moment.
//     *
//     * @api   GET /learning/evidence/{id}
//     * @since 20.2
//     */
//    public function getMoment(): void
//    {
//    }


    /**
     * Retrieve a user's learning moments.
     *
     * @api   GET /learning/evidence/user/{userId}
     * @since 20.2
     *
     * @param User $user
     */
    public function getMomentsForUser(User $user): void
    {
        // TODO add cursor for moments
        $encodedCursor = $this->request->query->get('cursor');
        $cursor        = new Cursor($this->mapper, $user);
        $data          = $cursor->retrieveResults(null);
        $mappedData    = $data
            ->map(
                new Translator\ModelToArray(
                    new User\Translator\ModelToArray(),
                    new Storage\Translator\ModelToArray(),
                ),
            )
            ->reindex()
            ->map(
                // Scrub body HTML, as there's no guarantee it has been scrubbed
                // elsewhere (and Vue can't do it)
                static function (array $item): array {
                    $item['body'] = Scrub::html($item['body']);
                    return $item;
                }
            );
        $this->response->content->set(
            $this->buildJsonOutput([
                'data'     => $mappedData,
                'metadata' => [
                    'count' => $cursor->retrieveResultCount(),
                    'cursor' => [
                        'current' => $encodedCursor ?? $cursor->current($data),
                        'next'    => $cursor->next($data),
                    ],
                ],
            ])
        );
    }


    /**
     * Post a JSON representation of one or more learning moments.
     *
     * @api   POST /learning/evidence/
     * @since 20.2
     */
    public function postMoments(): void
    {
    }


//    /**
//     * Post a JSON representation of a learning moment for a user.
//     *
//     * @api   POST /learning/evidence/user/{userId}
//     * @since 20.2
//     *
//     * @param User $user
//     */
//    public function postMomentForUser(User $user): void
//    {
//        $model = $this->mapper->createModel();
//        /** @var \stdClass $requestBody */
//        $requestBody = $this->request->content->get();
//        $model
//            ->setBody($requestBody->body)
//            ->setRecipient($user)
//            ->setCreator($this->getUser());
//        $this->mapper->save($model);
//        $requestBody->id = $model->getId();
//        $this->response->content->set(
//            $this->buildJsonOutput($requestBody)
//        );
//    }


    /**
     * Post the same learning moment to multiple users at once.
     *
     * @api   POST /learning/evidence/user/{userIds}/create
     * @since 20.2
     *
     * @param Collection|User[] $users
     * @throws \Schoolbox\Mapper\Exception
     */
    public function postMomentForUsers(Collection $users): void
    {
        // requestBody is a stdclass from JSON, I'd rather work with arrays:
        // encode then decode
        $requestBody = json_decode(json_encode($this->request->content->get()), true);


        // Save the evidence
        $evidenceCollection = $this->mapper->createCollection();
        $baseModel          = (new Translator\ArrayToModel(new CollectorFactory()))($requestBody)
            ->setCreator($this->getUser());
        foreach ($users as $user) {
            $evidenceCollection->add(
                (clone $baseModel)->setRecipient($user)
            );
        }
        $this->mapper->save($evidenceCollection);
        $this->response->content->set(
            $this->buildJsonOutput(
                $evidenceCollection->map(
                    new Translator\ModelToArray(
                        new User\Translator\ModelToArray(),
                        new Storage\Translator\ModelToArray(),
                    ),
                )
            )
        );
    }


//    /**
//     * Delete a single moment.
//     *
//     * @api   DELETE /learning/evidence/{id}
//     * @since 20.2
//     */
//    public function deleteMoment(): void
//    {
//    }
}
