<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Web\Controller\Base;
use Schoolbox\API\Translator\ModelToArray\Composite;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Collection;
use Schoolbox\Discussion\Comment;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray\Factory;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\GenericZone;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\TokenReplacer;
use Schoolbox\User;

/**
 * Controller for learning moments.
 */
class Controller extends Base
{

    /**
     * @var Mapper
     */
    private Mapper $mapper;


    /**
     * @var Comment\Mapper
     */
    private Comment\Mapper $commentMapper;


    /**
     * Constructor
     *
     * @param Mapper         $mapper
     * @param Comment\Mapper $commentMapper
     */
    public function __construct(Mapper $mapper, Comment\Mapper $commentMapper)
    {
        $this->mapper        = $mapper;
        $this->commentMapper = $commentMapper;
    }


    /**
     * Redirect to the create form.
     *
     * This takes a list of user IDs in the 'userId' query string parameter.
     *
     * @api   GET /learning/evidence/user/create
     * @since 20.2
     */
    public function redirectToCreateForm(): void
    {
        $this->response->redirect->to(
            $this->addQueryStringParamsToPath(
                $this->router->generate(
                    'learningMomentEvidence.getCreateForm',
                    ['userIds' => $this->request->query->get('userIds')]
                ),
                [Redirect::RETURN_URL, 'momentType']
            )
        );
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
        $momentType = $this->request->query->get('momentType') ?: Model::MOMENT_TYPE_TEXT;
        $template   = $this->getTemplateFromType($momentType);
        $params = [
            'users'                    => $users,
            'userIds'                  => $users->getPropertyValues('id'),
            'otherUsers'               => $otherUsers,
            'sharingForSelf'           => $sharingForSelf,
            'curriculumTaggingEnabled' => $this->curriculumTaggingEnabled(),
            'isCreate'                 => true,
            'momentType'               => $momentType,
            'zone'                     => $this->buildAttachzone($momentType)->getTwigContext()
        ];

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
        $translator = $this->buildTranslatorWithComments(10);

        $item = $translator($evidence);

        /* Decide whether the current user can comment on this student's learning moments
         *
         * Staff and student can always comment
         * Parents can comment if the config allows
         * Everyone else cannot
         */
        $learningMomentCanComment = (
            $this->getUser()->getRole()->isStaff()
            || $this->getUser()->getRole()->isStudent()
            || ($this->getUser()->getRole()->isParent() && $this->getConfigLoader()->get('evidence_parent_comment'))
        );

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningMomentEvidence/detail.twig',
                [
                    'evidence'                 => $item,
                    'curriculumTaggingEnabled' => $this->getConfigLoader()->get('curriculum_tagging_enabled'),
                    'learningMomentCanComment' => $learningMomentCanComment,
                ]
            )
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
        $encodedCursor = $this->request->query->get('cursor');
        $cursor        = new Cursor($this->mapper, $user);
        $data          = $cursor->retrieveResults($encodedCursor);
        $mappedData    = $data
            ->map($this->buildTranslatorWithComments(2))
            ->reindex();

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
     * @throws MapperException
     */
    public function postMomentForUsers(Collection $users): void
    {
        // requestBody is a stdclass from JSON, I'd rather work with arrays:
        // encode then decode
        $requestBody = json_decode(json_encode($this->request->content->get()), true);


        // Save the evidence
        $evidenceCollection = $this->mapper->createCollection();
        $baseModel          = (new Translator\ArrayToModel\MutableFieldsOnly(new CollectorFactory()))($requestBody)
            ->setCreator($this->getUser());
        $tokenReplacer      = new TokenReplacer\Student(new TokenReplacer\Simple());
        foreach ($users as $user) {
            $evidenceModel = clone $baseModel;
            $evidenceModel = $evidenceModel
                ->setRecipient($user)
                ->setBody($tokenReplacer->replace($evidenceModel->getBody(), $user));
            $evidenceCollection->add($evidenceModel);
        }
        $this->mapper->save($evidenceCollection);
        $this->response->content->set(
            $this->buildJsonOutput(
                $evidenceCollection->map(
                    new Translator\ModelToArray\Base(
                        new User\Translator\Author\ModelToArray(),
                        new Storage\Translator\ModelToArray(new PrefixGenerator($this->getConfigLoader())),
                        new ScrubAdapter(),
                    ),
                )
            )
        );
    }


    /**
     * Display the modify form for a single moment.
     *
     * @api   GET /learning/evidence/{id}/modify
     * @since 20.2
     * @param Model $evidence
     */
    public function getModifyForm(Model $evidence): void
    {
        // Which variant is being shown?
        // * text
        // * upload
        $momentType = $evidence->getType();
        $template   = $this->getTemplateFromType($momentType);
        $params     = [
            'users'                    => $evidence->getRecipient(),
            'userIds'                  => $evidence->getRecipient()->getId(),
            'otherUsers'               => [],
            'sharingForSelf'           => $this->getUser() === $evidence->getRecipient(),
            'curriculumTaggingEnabled' => $this->curriculumTaggingEnabled(),
            'isCreate'                 => false,
            'momentType'               => $momentType,
            'curriculumCodes'          => $evidence->getCurriculumNodes()?->reindex(),
        ];
        $zone       = $this->buildAttachzone($momentType);
        if ($evidence->getStorage()) {
            $zone->attachFiles([$evidence->getStorage()->getHash()]);
        }
        $params['zone']     = $zone->getTwigContext();
        $params['evidence'] = $evidence;

        $this->response->content->set(
            $this->buildHtmlOutput(
                $template,
                $params
            )
        );
    }


    /**
     * Updates a single moment.
     *
     * @api   PUT /learning/evidence/{id}/modify
     * @since 20.2
     * @param Model $evidence
     */
    public function putEvidence(Model $evidence): void
    {
        $requestBody       = json_decode(json_encode($this->request->content->get()), true);
        $requestTranslator = new Translator\ArrayToModel\MutableFieldsOnly(new CollectorFactory());
        $requestTranslator->setCurriculumTaggingEnabled($this->curriculumTaggingEnabled());
        $tokenReplacer     = new TokenReplacer\Student(new TokenReplacer\Simple());
        $evidence          = $requestTranslator($requestBody, $evidence)
            ->setBody($tokenReplacer->replace($evidence->getBody(), $evidence->getRecipient()));

        $this->mapper->save($evidence);

        $responseTranslator = new Translator\ModelToArray\Base(
            new User\Translator\Author\ModelToArray(),
            new Storage\Translator\ModelToArray(new PrefixGenerator($this->getConfigLoader())),
            new ScrubAdapter(),
        );
        $this->response->content->set(
            $this->buildJsonOutput(
                $responseTranslator($evidence)
            )
        );
    }


    /**
     * Delete a single moment.
     *
     * @api   DELETE /learning/evidence/{id}
     * @since 20.2
     * @param Model $evidence
     * @throws MapperException
     */
    public function deleteEvidence(Model $evidence): void
    {
        $this->mapper->delete($evidence);
    }


    /**
     * Get a list of available tags
     *
     * @api   GET /evidence/tags
     * @since 20.2
     */
    public function getTags()
    {
        /** @var \Schoolbox\Learning\Moment\Evidence\Tag\Mapper $tagMapper */
        $tagMapper = $this->mapper->getJoinMapper('evidence_tag');
        $tags      = $tagMapper->findAll();
        $output    = [];
        /** @var Tag\Model $tag */
        foreach ($tags as $tag) {
            $output[] = [
                'id'   => $tag->getId(),
                'name' => $tag->getName(),
            ];
        }
        $this->getResponse()->content->set($this->buildJsonOutput($output));
    }


    /**
     * Helper for building an attachzone for this controller.
     *
     * @param string $momentType
     * @return GenericZone
     */
    private function buildAttachzone(string $momentType): GenericZone
    {
        $zone = new GenericZone('storage', StorageMapper::getInstance());
        $zone->setAttachmentsLabel('')
             ->setAttachPrompt('upload files to this location')
             ->setMimeFilter('*/*')
             ->setFileVar('file')
             ->setMulti(false)
             ->setThumbnailSize(64)
             ->setModalTitle('Select file');
        switch ($momentType) {
            case Model::MOMENT_TYPE_PHOTO:
                $zone->setAttachPrompt('take a picture')
                     ->setIconClass('icon-camera')
                     ->setUseModal(false)
                     ->setCapture(true)
                     ->setMimeFilter('image/*');
                break;
            case Model::MOMENT_TYPE_AUDIO:
                $zone->setAttachPrompt('record audio')
                     ->setIconClass('icon-audio')
                     ->setUseModal(false)
                     ->setCapture(true)
                     ->setMimeFilter('audio/*');
                break;
            case Model::MOMENT_TYPE_VIDEO:
                $zone->setAttachPrompt('record video')
                     ->setIconClass('icon-video')
                     ->setUseModal(false)
                     ->setCapture(true)
                     ->setMimeFilter('video/*');
                break;
            case Model::MOMENT_TYPE_UPLOAD:
            case Model::MOMENT_TYPE_TEXT:
            default:
                break;
        }
        return $zone;
    }


    /**
     * Finds template name from moment type
     * @param string $momentType
     * @return string
     */
    private function getTemplateFromType(string $momentType): string
    {
        switch ($momentType) {
            case Model::MOMENT_TYPE_PHOTO:
                return '@learningMomentEvidence/form/photo.twig';
            case Model::MOMENT_TYPE_AUDIO:
                return '@learningMomentEvidence/form/audio.twig';
            case Model::MOMENT_TYPE_VIDEO:
                return '@learningMomentEvidence/form/video.twig';
            case Model::MOMENT_TYPE_UPLOAD:
                return '@learningMomentEvidence/form/upload.twig';
            case Model::MOMENT_TYPE_TEXT:
            default:
                return '@learningMomentEvidence/form/text.twig';
        }
    }


    /**
     * Builds a translator which contains comments.
     *
     * @param int $commentLimit
     * @return Composite
     */
    private function buildTranslatorWithComments(int $commentLimit): Composite
    {
        return (new Factory(
            $this->router,
            $this->authorizer,
            $this->getUser(),
            $this->commentMapper,
            new PrefixGenerator($this->getConfigLoader()),
        ))->buildWithComments($commentLimit);
    }

    /**
     * Checks whether curriculum tagging is enabled
     */
    private function curriculumTaggingEnabled(): bool
    {
        return $this->getUser()->getRole()->isStaff() && $this->getConfigLoader()->get('curriculum_tagging_enabled');
    }
}
