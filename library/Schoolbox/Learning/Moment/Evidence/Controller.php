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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Discussion\Comment;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Moment\Evidence\Moderation\ModerationRequired;
use Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray\Factory;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\GenericZone;
use Schoolbox\Resource\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\TokenReplacer;
use Schoolbox\User;
use Schoolbox\Editor\Templates\Helper as EditorTemplateHelper;

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
     * Helper for editor templates.
     */
    private EditorTemplateHelper $editorTemplateHelper;


    /**
     * Constructor
     *
     * @param Mapper         $mapper
     * @param Comment\Mapper $commentMapper
     * @param EditorTemplateHelper    $editorTemplateHelper
     */
    public function __construct(Mapper $mapper, Comment\Mapper $commentMapper, EditorTemplateHelper $editorTemplateHelper)
    {
        $this->mapper        = $mapper;
        $this->commentMapper = $commentMapper;
        $this->editorTemplateHelper = $editorTemplateHelper;
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
        $this->response = $this->redirectFound(
            $this->addQueryStringParamsToPath(
                $this->routeGenerator->generate(
                    'learningMomentEvidence.getCreateForm',
                    ['userIds' => $this->requestQuery->get('userIds')]
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
        $momentType = $this->requestQuery->get('momentType') ?: Model::MOMENT_TYPE_TEXT;
        $template   = $this->getTemplateFromType($momentType);

        /* Students get their learning moment moderation requirement tested;
         * parents and teachers bypass this check
         */
        $moderationRequired = false;

        if ($this->getUser()->getRole()->isStudent()) {
            $moderationRequired = (new ModerationRequired())->isSatisfiedBy($this->getConfigLoader()->get(ConfigMapper::NAME_LEARNING_MOMENTS_MODERATION_ENABLED), $this->getUser()->getYear());
        } elseif (1 === $users->count() && $this->getUser()->getRole()->isParent()) {
            /* If the current user is a parent, and the list of recipients is exactly one,
             * then the parent is creating a learning moment for one of their children via their activity feed.
             * Spawn the Submit For Approval button for parents so their learning moments enter moderation queue.
             *
             * This forces parents into the same moderation workflow as their children. ALTERNATIVELY you could
             * require the Learning Moment Moderation role permission on various parent roles; this could allow
             * some Parent roles to have moderation requirements and others can Publish immediately to the world.
             *
             * Note:
             * This is purely circumstantial since parents can't spawn learning moments for multiple people.
             */
            $moderationRequired = (new ModerationRequired())->isSatisfiedBy($this->getConfigLoader()->get(ConfigMapper::NAME_LEARNING_MOMENTS_MODERATION_ENABLED), $users->first()->getYear());
        }

        $params = [
            'evidence'                 => $this->mapper->createModel(),
            'users'                    => $users,
            'userIds'                  => $users->getPropertyValues('id'),
            'otherUsers'               => $otherUsers,
            'sharingForSelf'           => $sharingForSelf,
            'curriculumTaggingEnabled' => $this->curriculumTaggingEnabled(),
            'moderationRequired'       => $moderationRequired,
            'isCreate'                 => true,
            'momentType'               => $momentType,
            'zone'                     => $this->buildAttachzone($momentType)->getTwigContext(),
            'editorTemplates'          => $this->editorTemplateHelper->getTemplatesForCategory(),
        ];

        $this->response = $this->responseFromTemplate($template, $params);
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

        $this->response = $this->responseFromTemplate(
            '@learningMomentEvidence/detail.twig',
            [
                'evidence'                 => $item,
                'curriculumTaggingEnabled' => $this->getConfigLoader()->get('curriculum_tagging_enabled'),
                'learningMomentCanComment' => $learningMomentCanComment,
            ]
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
        $encodedCursor = $this->requestQuery->get('cursor');
        $cursor        = new Cursor($this->mapper, $user);
        $data          = $cursor->retrieveResults($encodedCursor);
        $mappedData    = $data
            ->map($this->buildTranslatorWithComments(2))
            ->reindex();

        $this->response = $this->responseAsJson([
            'data'     => $mappedData,
            'metadata' => [
                'count'  => $cursor->retrieveResultCount(),
                'cursor' => [
                    'current' => $encodedCursor ?? $cursor->current($data),
                    'next'    => $cursor->next($data),
                ],
            ],
        ]);
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
    public function postMomentForUsers(Collection $users, string $defaultState = null): void
    {
        /* Since this is a collection of users, each of whom might have a unique
         * configuration (e.g. some year levels require moderation, some don't)
         * you'll end up with an $evidenceCollection of mixed results; some will
         * submit and publish immediately whilst some end up in a moderation queue.
         */
        $globalConfig = (bool)$this->getConfigLoader()->get(ConfigMapper::NAME_LEARNING_MOMENTS_MODERATION_ENABLED);
        $moderationRequired = (new ModerationRequired())->isSatisfiedBy($globalConfig, $this->getUser()->getYear());

        $requestBody = $this->request->getParsedBody();

        //unless there are mapper errors (and those will change it to 500 as required
        //set the return code to 201 (Created)

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

            if (null !== $defaultState) {
                /* Spawn a learning moment in a particular state
                 */
                $this->updateState($evidenceModel, $defaultState);
            } elseif ($moderationRequired) {
                /* Accelerate into the next state based on configs and year level settings
                 */
                $evidenceModel->draft();
            } else {
                /* Does not require moderation; just send it into the world
                 */
                $evidenceModel->publish($this->getUser());
            }

            $evidenceCollection->add($evidenceModel);
        }
        $this->mapper->save($evidenceCollection);
        $this->response = $this->responseAsJson(
            $evidenceCollection->map(
                new Translator\ModelToArray\Base(
                    new User\Translator\Author\ModelToArray(),
                    new Storage\Translator\ModelToArray(new PrefixGenerator($this->getConfigLoader())),
                    new ScrubAdapter(),
                ),
            )
        )->withStatus(201);
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

        /* Attachzone stuff for existing attachments
         */
        $zone = $this->buildAttachzone($momentType);
        if ($evidence->getStorage()) {
            $zone->attachFiles([$evidence->getStorage()->getHash()]);
        }

        /* Students get their learning moment moderation requirement tested;
         * parents and teachers bypass this check
         */
        $moderationRequired = false;

        if ($this->getUser()->getRole()->isStudent()) {
            $moderationRequired = (new ModerationRequired())->isSatisfiedBy($this->getConfigLoader()->get(ConfigMapper::NAME_LEARNING_MOMENTS_MODERATION_ENABLED), $this->getUser()->getYear());
        } elseif ($this->getUser()->getRole()->isParent()) {
            $moderationRequired = (new ModerationRequired())->isSatisfiedBy($this->getConfigLoader()->get(ConfigMapper::NAME_LEARNING_MOMENTS_MODERATION_ENABLED), $evidence->getRecipient()->getYear());
        }

        $params = [
            'evidence'                 => $evidence,
            'users'                    => $evidence->getRecipient(),
            'userIds'                  => $evidence->getRecipient()->getId(),
            'otherUsers'               => [],
            'sharingForSelf'           => $this->getUser() === $evidence->getRecipient(),
            'curriculumTaggingEnabled' => $this->curriculumTaggingEnabled(),
            'moderationRequired'       => $moderationRequired,
            'isCreate'                 => false,
            'momentType'               => $momentType,
            'curriculumCodes'          => $evidence->getViewableCurriculumNodes()?->reindex(),
            'zone'                     => $zone->getTwigContext(),
            'hasComments'              => $this->hasComments($evidence),
            'returnUrl'                => $this->requestQuery->get('returnUrl'),
            'editorTemplates'          => $this->editorTemplateHelper->getTemplatesForCategory(),
        ];

        $this->response = $this->responseFromTemplate(
            $template,
            $params
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
        $requestBody       = $this->request->getParsedBody();

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
        $this->response     = $this->responseAsJson($responseTranslator($evidence));
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
        //unless there are mapper errors, set the return status code to 204 (No Content)
        $this->mapper->delete($evidence);
        $this->response = $this->responseEmpty(204);
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
        $this->response = $this->responseAsJson($output);
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
     * Find out if there any comments related to this learning moment
     * Finding at least one comment means there's stuff to delete here
     *
     * @param Model $evidence
     * @return bool
     */
    private function hasComments(Model $evidence): bool
    {
        $translator = $this->buildTranslatorWithComments(1);
        $item = $translator($evidence);

        return (bool)\count($item['discussionThread']['children']['data'] ?? []);
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
            $this->routeGenerator,
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


    /* State changes that bypass, or precede, the moderation workflow
     * such as drafting, submitting for moderation and publishing immediately to the world
     */


    /**
     * Craft a new learning moment directly into the Draft state via the Class List "Share a Learning Moment"
     *
     * This is almost a direct replica of postMomentForUsers()
     *
     * @api   POST /learning/evidence/user/{userIds}/draft
     * @param Collection $users
     * @return void
     * @throws MapperException
     */
    public function postDraft(Collection $users): void
    {
        $this->postMomentForUsers($users, Model::STATUS_DRAFT);
    }


    /**
     * Its express purpose is to put the existing $evidence into Draft along with any alterations
     * to its content such as body, tags, curric nodes, etc.
     *
     * @param Model $evidence
     * @return void
     * @throws Exception
     * @throws MapperException
     *@api   PUT /learning/evidence/{id}/submit
     */
    public function putDraft(Model $evidence): void
    {
        $this->putWithState($evidence, Model::STATUS_DRAFT);
    }


    /**
     * Craft a new learning moment directly into the Submitted state, skipping the Drafting state.
     * This is almost a direct replica of postMomentForUsers() - it just sets a state on the new
     * instance rather than assume a Draft state.
     *
     * @api   POST /learning/evidence/{id}/submit
     * @param Collection $users
     * @return void
     * @throws MapperException
     */
    public function postSubmit(Collection $users): void
    {
        $this->postMomentForUsers($users, Model::STATUS_SUBMITTED);
    }


    /**
     * Its express purpose is to put the existing $evidence into Submitted along with any alterations
     * to its content such as body, tags, curric nodes, etc.
     *
     * @param Model $evidence
     * @return void
     * @throws Exception
     * @throws MapperException
     *@api   PUT /learning/evidence/{id}/submit
     */
    public function putSubmit(Model $evidence): void
    {
        $this->putWithState($evidence, Model::STATUS_SUBMITTED);
    }


    /**
     * Craft a new learning moment directly into the Published state, skipping the Submitted state.
     * This is almost a direct replica of postMomentForUsers() - it just sets a state on the new
     * instance rather than assume a Submitted state.
     *
     * @api   POST /learning/evidence/{id}/publish
     * @param Collection $users
     * @return void
     * @throws MapperException
     */
    public function postPublish(Collection $users): void
    {
        $this->postMomentForUsers($users, Model::STATUS_PUBLISHED);
    }


    /**
     * Its express purpose is to put the existing $evidence into Published along with any alterations
     * to its content such as body, tags, curric nodes, etc.
     *
     * @param Model $evidence
     * @return void
     * @throws Exception
     * @throws MapperException
     *@api   PUT /learning/evidence/{id}/publish
     */
    public function putPublish(Model $evidence): void
    {
        $this->putWithState($evidence, Model::STATUS_PUBLISHED);
    }


    /**
     * Place $evidence into the new state $state, along with any modifications made to its
     * body, tags, curric nodes, etc. via the Modify form. This might not always be how we
     * putWithState() since you can do this directly from the CTAs on the Activity Feed.
     *
     * @param Model $evidence
     * @param string $state
     * @return void
     * @throws Exception
     * @throws MapperException
     */
    private function putWithState(Model $evidence, string $state): void
    {
        $requestBody = $this->request->getParsedBody();

        /* This might be empty if submitting directly from the activity feed;
         *
         * It'll be an array of stuff from the Modify form though, at which point we take what was
         * written there and update our model here.
         */
        if (!empty($requestBody)) {
            $requestTranslator = new Translator\ArrayToModel\MutableFieldsOnly(new CollectorFactory());
            $requestTranslator->setCurriculumTaggingEnabled($this->curriculumTaggingEnabled());
            $tokenReplacer = new TokenReplacer\Student(new TokenReplacer\Simple());
            $evidence = $requestTranslator($requestBody, $evidence)->setBody($tokenReplacer->replace($evidence->getBody(), $evidence->getRecipient()));
        }

        /* Shove it into the new state, $state
         */
        $this->updateState($evidence, $state);

        /* Save it
         */
        $this->mapper->save($evidence);

        $responseTranslator = new Translator\ModelToArray\Base(
            new User\Translator\Author\ModelToArray(),
            new Storage\Translator\ModelToArray(new PrefixGenerator($this->getConfigLoader())),
            new ScrubAdapter(),
        );

        $this->response = $this->responseAsJson($responseTranslator($evidence));
    }


    /**
     * Place $evidence into the new state $state
     *
     * @param Model $evidence
     * @param string $state
     * @return void
     */
    private function updateState(Model $evidence, string $state): void
    {
        if (Model::STATUS_DRAFT === $state) {
            $evidence->draft();
        } elseif (Model::STATUS_SUBMITTED === $state) {
            $evidence->submit($this->getUser());
        } elseif (Model::STATUS_PUBLISHED === $state) {
            $evidence->publish($this->getUser());
        } elseif (Model::STATUS_REJECTED === $state) {
            $evidence->reject($this->getUser());
        }
    }
}
