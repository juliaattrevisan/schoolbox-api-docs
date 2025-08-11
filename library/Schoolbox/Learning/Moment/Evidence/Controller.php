<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\API\Translator\ModelToArray\Composite;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Discussion\Comment;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Moment\Evidence\Moderation\ModerationRequired;
use Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray\Factory;
use Schoolbox\Learning\Moment\NextRoutes;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\GenericZone;
use Schoolbox\Resource\Exception;
use Schoolbox\Resource\Folder;
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
     * Cohort Report for learning moments looks seven days in the past by default
     */
    private const COHORT_REPORT_PAST_THRESHOLD = -7;


    /**
     * Constructor
     *
     * @param Mapper $mapper
     * @param Comment\Mapper $commentMapper
     * @param EditorTemplateHelper    $editorTemplateHelper
     * @param \Schoolbox\UserMapper $userMapper
     * @param \External_Class $externalClass
     * @param \Schoolbox\Permission\Access\UserAccessMapper $userAccessMapper
     */
    public function __construct(
        private Mapper $mapper,
        private Comment\Mapper $commentMapper,
        private EditorTemplateHelper $editorTemplateHelper,
        private \Schoolbox\UserMapper $userMapper,
        private \External_Class $externalClass,
        private \Schoolbox\Permission\Access\UserAccessMapper $userAccessMapper,
    ) {
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
     * @api   PUT /learning/evidence/{id}/submit
     * @param Model $evidence
     * @return void
     * @throws Exception
     * @throws MapperException|RouteNotFound
     */
    public function putDraft(Model $evidence): void
    {
        $this->response = $this->responseAsJson($this->putWithState($evidence, Model::STATUS_DRAFT));
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
     * @api   PUT /learning/evidence/{id}/submit
     * @param Model $evidence
     * @return void
     * @throws Exception
     * @throws MapperException|RouteNotFound
     */
    public function putSubmit(Model $evidence): void
    {
        $this->response = $this->responseAsJson($this->putWithState($evidence, Model::STATUS_SUBMITTED));
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
     * @api   PUT /learning/evidence/{id}/publish
     * @param Model $evidence
     * @return void
     * @throws Exception
     * @throws MapperException|RouteNotFound
     */
    public function putPublish(Model $evidence): void
    {
        $this->response = $this->responseAsJson($this->putWithState($evidence, Model::STATUS_PUBLISHED));
    }


    /**
     * Place $evidence into the new state $state, along with any modifications made to its
     * body, tags, curric nodes, etc. via the Modify form. This might not always be how we
     * putWithState() since you can do this directly from the CTAs on the Activity Feed.
     *
     * @param Model $evidence
     * @param string $state
     * @return array
     * @throws Exception
     * @throws MapperException|RouteNotFound
     */
    private function putWithState(Model $evidence, string $state): array
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

        $translated = $responseTranslator($evidence);

        /* A shortcut to urls for changing states in this learning moment
         */
        $translated['_links'] = $this->getNextRoutes($evidence);

        return $translated;
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


    /**
     * Generates a json payload that tells you about learning moments for any of $folder's students
     * that were created between the $from and $to dates, grouped by date, sorted reverse-chronologically.
     *
     * By default, it'll assume $from and $to to be a 7-day range:
     *   - start of the day 7 days ago
     *   - end of today
     *
     * @param Folder\Model $folder
     * @return void
     */
    public function getCohortReportPayloadDefault(Folder\Model $folder): void
    {
        $this->getCohortReportPayload(
            $folder,
            \Alaress_Date_Format::now()->addDays(self::COHORT_REPORT_PAST_THRESHOLD)->startOfDay(),
            \Alaress_Date_Format::now()->endOfDay()
        );
    }


    /**
     * Generates a json payload that tells you about learning moments for any of $folder's students
     * that were created between the $from and $to dates, grouped by date, sorted reverse-chronologically.
     *
     * @param Folder\Model $folder
     * @param string $fromDate
     * @param string $toDate
     * @return void
     */
    public function getCohortReportPayloadRange(Folder\Model $folder, string $fromDate, string $toDate): void
    {
        $this->getCohortReportPayload(
            $folder,
            (new \Alaress_Date_Format($fromDate, \Alaress_Date_Format::DATABASE_DATE_FORMAT))->startOfDay(),
            (new \Alaress_Date_Format($toDate, \Alaress_Date_Format::DATABASE_DATE_FORMAT))->endOfDay()
        );
    }


    /**
     * Generates a json payload that tells you about learning moments for any of $folder's students
     * that were created between the $from and $to dates, grouped by date, sorted reverse-chronologically.
     *
     * By default, it'll assume $from and $to to be a 7-day range:
     *   - start of the day 7 days ago
     *   - end of today
     *
     * @param Folder\Model $folder
     * @param \Alaress_Date_Format $from
     * @param \Alaress_Date_Format $to
     * @return void
     * @throws MapperException
     * @throws NotFound
     * @throws RouteNotFound
     */
    public function getCohortReportPayload(Folder\Model $folder, \Alaress_Date_Format $from, \Alaress_Date_Format $to): void
    {
        $groupIds = [];
        $classIds = [];

        /* Check for a maximum length of time of [now, -1 year at start of day]
         */
        $threshold = \Alaress_Date_Format::now()->addYears(-1)->startOfDay();

        if ($from->lt($threshold)) {
            $from = $threshold;
        }

        /* Get a list of student ids that are in $folder and use a hash lookup
         * to cross reference everything we find later
         */
        if ($folder->isGroup()) {
            $userAccessList = $this->userAccessMapper->findForFolder($folder)
                ->indexByCallback(static function (UserAccess $userAccess) {
                    return $userAccess->getUser()->getId();
                });

            if ($userAccessList->count()) {
                $groupIds = $this->userMapper->findByColumn('id', $userAccessList->keys())
                    ->filter(function (\Schoolbox\User $user) {
                        return ($user->getRole()->isStudent());
                    })
                    ->indexBy('id')
                    ->keys();
            }
        }

        /* Students of a class-coded folder
         */
        if ('' !== $folder->getCodeString()) {
            $students = array_column($this->externalClass->getStudents($folder->getCodeString()), 'synergy_id');

            /* These are the user instances associated with $folder's class list of students
             * We'll use this to interset with learning moments $recipients
             */
            if (\count($students)) {
                $classIds = $this->userMapper->findByExternalIds($students, \Schoolbox\Role::STUDENT)
                    ->indexBy('id')
                    ->keys();
            }
        }

        /* Combine stacks
         */
        $ids = array_values(array_unique(array_merge($groupIds, $classIds)));

        /* Find all the learning moments in the specified date range
         */
        $payload = [];

        if (\count($ids)) {
            /* Only care about moments whose recipients are in the set of users associated
             * with $folder: otherwise ignore the learning moment
             */
            $pb = $this->mapper->getPredicateBuilder()
                ->between('updated_at', $from, $to)
                ->in('user_id', $ids);

            $moments = $this->mapper->findByPredicates($pb)->sortBy('updatedAt', 'DESC');

            foreach ($moments as $moment) {
                /* Accumulate a list of associated files for this learning moment
                 * At the moment there's gonna be just one but SA-20433 causes this to be multiple
                 */
                $files = [];

                if (null !== $moment->getStorage()) {
                    $files = [
                        [
                            'hash' => $moment->getStorage()->getHash(),
                            'filename' => $moment->getStorage()->getFilename(),
                            'mime' => $moment->getStorage()->getMime(),
                            'icon' => $moment->getStorage()->getMimetype()->getIconFile(),
                        ],
                    ];
                }

                /* This is a stack because empty payloads result in a datatype difference in frontend world:
                 * empty payloads emerge as [], but non-empty payloads emerge as {}
                 */
                $payload[] = [
                    'id' => $moment->getId(),
                    'body' => $moment->getBody(),
                    'files' => $files,
                    'recipient' => [
                        'id' => $moment->getRecipient()->getId(),
                        'externalId' => $moment->getRecipient()->getExternalId(),
                        'fullname' => $moment->getRecipient()->getFullname(),
                        'firstname' => $moment->getRecipient()->getFirstname(),
                        'lastname' => $moment->getRecipient()->getLastname(),
                        'role' => $moment->getRecipient()->getPreferredTitle()
                    ],
                    'creator' => [
                        'id' => $moment->getCreator()->getId(),
                        'externalId' => $moment->getCreator()->getExternalId(),
                        'fullname' => $moment->getCreator()->getFullname(),
                        'firstname' => $moment->getCreator()->getFirstname(),
                        'lastname' => $moment->getCreator()->getLastname(),
                    ],
                    /* This is always set
                     */
                    'createdAt' => [
                        'timestamp' => $moment->getCreatedAt()->getUnixTimestamp(),
                        'displayAs' => $moment->getCreatedAt()->getLocalShort()
                    ],
                    /* We don't store updatedBy, for whatever reason: I suppose it was presumed
                     * to always be the same as its author...
                     *
                     * Anyway, this is the localised version of config's date_short which you can use
                     * to group moments per day.
                     */
                    'updatedAt' => [
                        'timestamp' => $moment->getUpdatedAt()->getUnixTimestamp(),
                        'displayAs' => $moment->getUpdatedAt()->getLocalShort()
                    ],
                    /* Whether this moment had comments or not, which affects a user interaction
                     * where they have an opportunity to delete comments upon approval
                     */
                    'hasComments' => $this->hasComments($moment),
                    // TODO State-specific metadata is currently not needed, but maybe needed later
                    //
                    //                    /* Metadata about the current state of a learning moment and any other details
                    //                     * regarding the current state, such as who actioned a state change etc.
                    //                     */
                    //                    'states' => [
                    //                        'current' => $moment->getCurrentState(),
                    //                        /* You'll be in one of three alternative states here: submitted/published/rejected
                    //                         */
                    //                        'submittedAt' => $moment->getSubmittedAt()?->getDatabase(),
                    //                        'submittedBy' => $moment->getSubmittedBy() ? [
                    //                            'id' => $moment->getSubmittedBy()->getId(),
                    //                            'externalId' => $moment->getSubmittedBy()->getExternalId(),
                    //                            'fullname' => $moment->getSubmittedBy()->getFullname(),
                    //                            'firstname' => $moment->getSubmittedBy()->getFirstname(),
                    //                            'lastname' => $moment->getSubmittedBy()->getLastname(),
                    //                        ] : null,
                    //                        'publishAt' => $moment->getPublishedAt()?->getDatabase(),
                    //                        'publishedBy' => $moment->getPublishedBy() ? [
                    //                            'id' => $moment->getPublishedBy()->getId(),
                    //                            'externalId' => $moment->getPublishedBy()->getExternalId(),
                    //                            'fullname' => $moment->getPublishedBy()->getFullname(),
                    //                            'firstname' => $moment->getPublishedBy()->getFirstname(),
                    //                            'lastname' => $moment->getPublishedBy()->getLastname(),
                    //                        ] : null,
                    //                        'rejectedAt' => $moment->getRejectedAt()?->getDatabase(),
                    //                        'rejectedBy' => $moment->getRejectedBy() ? [
                    //                            'id' => $moment->getRejectedBy()->getId(),
                    //                            'externalId' => $moment->getRejectedBy()->getExternalId(),
                    //                            'fullname' => $moment->getRejectedBy()->getFullname(),
                    //                            'firstname' => $moment->getRejectedBy()->getFirstname(),
                    //                            'lastname' => $moment->getRejectedBy()->getLastname(),
                    //                        ] : null
                    //                    ],
                    /* A shortcut to urls for changing states in this learning moment
                     */
                    '_links' => $this->getNextRoutes($moment),
                ];
            }
        }

        $this->response = $this->responseAsJson([
            'status'  => 200,
            'content' => $payload,
        ]);
    }


    /**
     * For a particular learning moment, decide what actions could happen next.
     *
     * @param Model $moment
     * @return array
     * @throws RouteNotFound
     */
    private function getNextRoutes(Model $moment): array
    {
        $links = (new NextRoutes())->generate($moment);

        /* Only get the routes you need
         */
        //        if ($links['submit']) {
        //            $links['submit'] = $this->getRouteIfAuthorized('learningMomentEvidence.putSubmit', [
        //                'evidenceId' => $moment->getId(),
        //                'creatorId' => $moment->getCreator()->getId(),
        //                'userId' => $moment->getRecipient()->getId(),
        //            ]);
        //        }

        if ($links['publish']) {
            $links['publish'] = $this->getRouteIfAuthorized('learningMomentEvidence.putPublish', [
                'evidenceId' => $moment->getId(),
                'creatorId' => $moment->getCreator()->getId(),
            ]);
        }

        if ($links['reject']) {
            $links['reject'] = $this->getRouteIfAuthorized('learningMomentModeration.putReject', [
                'evidenceId' => $moment->getId(),
            ]);
        }

        return $links;
    }
}
