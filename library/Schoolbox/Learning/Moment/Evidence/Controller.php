<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Alaress\Json\Decorator\PropertyDecorator;
use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Discussion\Comment;
use Schoolbox\Discussion\Thread;
use Schoolbox\Json\Decorator\LinkDecorator;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\GenericZone;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\User;
use Scrub;

/**
 * Controller for learning moments.
 */
class Controller extends Base
{
    private const MOMENT_TYPE_TEXT   = 'text';
    private const MOMENT_TYPE_UPLOAD = 'upload';
    private const MOMENT_TYPE_PHOTO  = 'photo';
    private const MOMENT_TYPE_AUDIO  = 'audio';
    private const MOMENT_TYPE_VIDEO  = 'video';


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
            case self::MOMENT_TYPE_PHOTO:
                $template       = '@learningMomentEvidence/form/photo.twig';
                $params['zone'] = $this->buildAttachzone()
                                       ->setAttachPrompt('take a picture')
                                       ->setIconClass('icon-camera')
                                       ->setUseModal(false)
                                       ->setCapture(true)
                                       ->setMimeFilter('image/*')
                                       ->getTwigContext();
                break;

            case self::MOMENT_TYPE_AUDIO:
                $template       = '@learningMomentEvidence/form/audio.twig';
                $params['zone'] = $this->buildAttachzone()
                                       ->setAttachPrompt('record audio')
                                       ->setIconClass('icon-audio')
                                       ->setUseModal(false)
                                       ->setCapture(true)
                                       ->setMimeFilter('audio/*')
                                       ->getTwigContext();
                break;

            case self::MOMENT_TYPE_VIDEO:
                $template       = '@learningMomentEvidence/form/video.twig';
                $params['zone'] = $this->buildAttachzone()
                                       ->setAttachPrompt('record video')
                                       ->setIconClass('icon-video')
                                       ->setUseModal(false)
                                       ->setCapture(true)
                                       ->setMimeFilter('video/*')
                                       ->getTwigContext();
                break;

            case self::MOMENT_TYPE_UPLOAD:
                $template       = '@learningMomentEvidence/form/upload.twig';
                $params['zone'] = $this->buildAttachzone()
                                       ->getTwigContext();
                break;

            case self::MOMENT_TYPE_TEXT:
            default:
                break;
        }

        $this->response->content->set(
            $this->buildHtmlOutput($template, $params)
        );
    }


    /**
     * Set up the detail page for a particular Evidence instance
     *
     * @param Model $evidence
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getEvidenceDetail(Model $evidence): void
    {
        $translator = new Translator\ModelToArray(
            new User\Translator\ModelToArray(),
            new Storage\Translator\ModelToArray(),
        );

        $item = $translator($evidence);
        $item = $this->generateLinks($item);

        /* TODO: Why wouldn't you just scrub this in ModelToArray
         */
        $item['body'] = Scrub::html($item['body']);

        $this->response->content->set($this->buildHtmlOutput('@learningMomentEvidence/detail.twig', [
            'evidence' => $item,
        ]));
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
        $encodedCursor = $this->request->query->get('cursor');
        $cursor        = new Cursor($this->mapper, $user);
        $data          = $cursor->retrieveResults($encodedCursor);
        $mappedData    = $data
            ->map(
                new Translator\ModelToArray(
                    new User\Translator\Author\ModelToArray(),
                    new Storage\Translator\ModelToArray(),
                ),
            )
            ->reindex()
            ->map(
                static function (array $item): array {
                    // Scrub body HTML, as there's no guarantee it has been
                    // scrubbed elsewhere (and Vue can't do it)
                    $item['body'] = Scrub::html($item['body']);
                    return $item;
                }
            )
            ->map(

                /* Add router-generated urls to every Evidence array
                 */
                function (array $item): array {
                    return $this->generateLinks($item);
                }
            );


        // Include some of the discussion thread's comments in this return.
        // (Say, the first two?)
        // The translator will need to know how to translate discussion threads.
        $commentMapper = Comment\Mapper::getInstance();
        $linkDecorator = new LinkDecorator($this->router, $this->authorizer, $this->getUser());
        $jsonDecorator = new PropertyDecorator(null, $linkDecorator);
        $mappedData    = $mappedData->map(function (array $item) use ($commentMapper, $linkDecorator, $jsonDecorator) {
            $item['_links']     = [];
            $evidence           = (new Model())->setId($item['id']);
            $discussionThreadId = $item['discussionThread']['id'] ?? null;
            if ($discussionThreadId) {
                $discussionThread = (new Thread\Model())->setId($discussionThreadId);
                $evidence->setDiscussionThread($discussionThread);

                $commentCursor  = new Comment\Cursor($commentMapper, $discussionThread);
                $results        = $commentCursor->retrieveResults(null);
                $encodedResults = $results
                    ->map(static function (Comment\Model $comment) use ($evidence) {
                        return new Discussion\Comment\ModelDecorator\Decorator($comment, $evidence);
                    });
                $encodedResults = $encodedResults
                    ->jsonSerialize($jsonDecorator, $linkDecorator);

                // Serialize the discussion thread using its decorator
                // TODO consider moving the links into the base data, as there should be a create thread link
                $item['discussionThread'] = (new Discussion\Thread\ModelDecorator\Decorator($discussionThread, $evidence))
                    ->jsonSerialize($jsonDecorator, $linkDecorator);

                // Serialize the thread's comments as well
                $item['discussionThread']['children'] = [
                    'data'     => $encodedResults,
                    'metadata' => [
                        'count'  => $commentCursor->retrieveResultCount(),
                        'cursor' => [
                            'current' => $commentCursor->current($results),
                            'next'    => $commentCursor->next($results),
                        ],
                    ],
                ];
            } else {
                // Output the create thread link.
                // TBD: is it output as a simple link, or a href/auth object?
                // The old Aura link outputs the object, newer links are output
                // as simple values (and are not output at all if permission denied)
                $routeParams = [
                    'contextType' => 'evidence',
                    'context'     => $evidence,
                    'contextId'   => $evidence->getId(),
                ];
                $createThreadLink = $this->getRouteIfAuthorized(
                    'evidenceDiscussionThreads.createThread',
                    $routeParams
                );
                if ($createThreadLink) {
                    $item['_links']['createThread'] = $createThreadLink;
                }
            }
            return $item;
        });


        $this->response->content->set(
            $this->buildJsonOutput([
                'data'     => $mappedData,
                'metadata' => [
                    'count'  => $cursor->retrieveResultCount(),
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
                        new User\Translator\Author\ModelToArray(),
                        new Storage\Translator\ModelToArray(),
                    ),
                )
            )
        );
    }


    /**
     * Delete a single moment.
     *
     * @api   DELETE /learning/evidence/{id}
     * @since 20.2
     * @param Model $evidence
     * @throws \Schoolbox\Mapper\Exception
     */
    public function deleteEvidence(Model $evidence): void
    {
        $this->mapper->delete($evidence);
    }


    /**
     * Helper for building an attachzone for this controller.
     *
     * @return GenericZone
     */
    private function buildAttachzone(): GenericZone
    {
        $zone = new GenericZone('storage', StorageMapper::getInstance());
        $zone->setAttachmentsLabel('')
             ->setAttachPrompt('upload files to this location')
             ->setMimeFilter('*/*')
             ->setFileVar('file')
             ->setMulti(false)
             ->setThumbnailSize(64)
             ->setModalTitle('Select file');
        return $zone;
    }


    /**
     * Generate a set of links relevant to $model
     *
     * This uses the router's getRouteIfAuthorized() method and some controller parts
     * to figure out which links are available and which aren't. We shouldn't provide
     * links that a person can't visit.
     *
     * The router and its mechanics are pretty solidly tied to controllers and I don't
     * necessarily want to inject an entire controller instance into the Translator
     * classes and assorted places like that
     *
     * @param array $evidence
     * @return array
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    private function generateLinks(array $evidence): array
    {
        $evidence['_links'] = [
            'detail' => $this->getRouteIfAuthorized('learningMomentEvidence.getEvidenceDetail', [
                'evidenceId' => $evidence['id'],
                'userId' => $evidence['recipient']['id']
            ]),

            /* TODO Figure out what the modify perms will be
             *  I added this here, it was in there SA-11246 screenshot.
             */
            'modify' => null,

            'delete' => $this->getRouteIfAuthorized('learningMomentEvidence.deleteEvidence', [
                'evidenceId' => $evidence['id'],
                'creatorId' => $evidence['creator']['id']
            ])
        ];

        return $evidence;
    }
}
