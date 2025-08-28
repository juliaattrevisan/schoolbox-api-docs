<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment;

use Alaress\Database\Predicate\Builder;
use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\API\Translator\ModelToArray\Composite;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Discussion\Comment;
use Schoolbox\Editor\Templates\Helper as EditorTemplateHelper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Moment\Cursor;
use Schoolbox\Learning\Moment\Model;
use Schoolbox\Learning\Moment\Moderation\ModerationRequired;
use Schoolbox\Learning\Moment\NextRoutes;
use Schoolbox\Learning\Moment\Translator\ModelToArray\Factory;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Exception;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Storage;
use Schoolbox\TokenReplacer;
use Schoolbox\User;

/**
 * Controller for learning moments.
 */
class Controller extends Base
{
    /**
     * Length of the blurb to show in the cohort report when there is evidence attached (ideally <= 3 lines)
     */
    private const COHORT_REPORT_BLURB_LENGTH = 180;

    /**
     * Length of the blurb to show in the cohort report when there is no other evidence attached
     */
    private const COHORT_REPORT_BLURB_LENGTH_EXTENDED = 400;

    /**
     * Constructor
     *
     * @param \Schoolbox\Learning\Moment\Mapper $mapper
     * @param Comment\Mapper $commentMapper
     * @param EditorTemplateHelper    $editorTemplateHelper
     * @param \Schoolbox\Permission\Access\UserAccessMapper $userAccessMapper
     * @param Storage\Translator\ModelToArray $storageTranslator
     * @param \Schoolbox\Resource\Folder\Mapper $folderMapper
     * @param \Schoolbox\Discussion\Thread\Mapper $discussionThreadMapper
     */
    public function __construct(
        private \Schoolbox\Learning\Moment\Mapper $mapper,
        private Comment\Mapper $commentMapper,
        private EditorTemplateHelper $editorTemplateHelper,
        private \Schoolbox\Permission\Access\UserAccessMapper $userAccessMapper,
        private Storage\Translator\ModelToArray $storageTranslator,
        private \Schoolbox\Resource\Folder\Mapper $folderMapper,
        private \Schoolbox\Discussion\Thread\Mapper $discussionThreadMapper,
    ) {
    }


    /**
     * Redirect to the create form.
     *
     * This takes a list of user IDs in the 'userId' query string parameter and forcibly
     * crafts an aura route using them, fitting the pattern "/user/{userIds}/create" which
     * isn't convenient to do in the javascript world.
     *
     * The rest of the querystring params need to be manually whitelisted so they can be
     * appended to this generated url
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
                    [
                        'userIds' => $this->requestQuery->get('userIds'),
                    ]
                ),
                /* Whitelist the querystring params from whence you came
                 * You can't just shove them onto the end: you have to add to this whitelist
                 */
                [
                    Redirect::RETURN_URL,
                    'momentType',
                    'folderId'
                ]
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

        /* These are folders that the recipient can see via their membership;
         * Note that a staff member might edit a student's learning moment, so you have to use
         * the recipient's user instance, not the viewer's
         */
        $folders = null;

        /* Students get their learning moment moderation requirement tested;
         * parents and teachers bypass this check
         */
        if ($this->getUser()->getRole()->isStudent()) {
            /* These are folders that the recipient can see via their membership;
             * Note that a staff member might edit a student's learning moment, so you have to use
             * the recipient's user instance, not the viewer's
             */
            $userAccesses = $this->userAccessMapper->findAllForUser($this->getUser(), $this->getUser()->isShowTimetable())
                ->filter(function (UserAccess $access) {
                    return UserAccess::SERVICE_HOMEPAGE === $access->getService();
                });

            if ($userAccesses->count()) {
                $folders = $this->folderMapper->findByColumn('id', $userAccesses->getPropertyValues('serviceIdentifier'));
            }
        } elseif (1 === $users->count()) {
            /* Crafting a learning moment for exactly one other person
             *
             * If the current user is a staff, and the list of recipients is exactly one,
             * it doesn't matter if you selected one fella from the class list, or
             * tried to share a learning moment directly from the recipient's activity feed;
             * the list of folders they can access is determinate.
             *
             * Otherwise, you have a mixed bag of people who have access to different folders
             * and that would make the ui really messy.
             */
            if ($this->getUser()->getRole()->isStaff()) {
                /* These are folders that the recipient can see via their membership;
                 * Note that a staff member might edit a student's learning moment, so you have to use
                 * the recipient's user instance, not the viewer's
                 */
                $userAccesses = $this->userAccessMapper->findAllForUser($users->first(), $users->first()->isShowTimetable())
                    ->filter(function (UserAccess $access) {
                        return UserAccess::SERVICE_HOMEPAGE === $access->getService();
                    });

                if ($userAccesses->count()) {
                    $folders = $this->folderMapper->findByColumn('id', $userAccesses->getPropertyValues('serviceIdentifier'));
                }
            }
        } elseif ($this->getUser()->getRole()->isStaff()) {
            /* If the current user is a staff and the list of recipients is more than one,
             * presume folder involvement to be specifically the Class List from whence you came
             * Request a collection of folders for data struct synonymity
             */
            $folders = $this->folderMapper->findByColumn('id', $this->requestQuery->get('folderId'));
        }

        /* This is usually empty when creating, except when you're creating by the class list;
         * that will have a folder already chosen
         */
        $selectedFolders = [];

        /* If folderId exists in the querystring params, use that to preselect
            * (you probably came here via the homepage component)
            */
        if ($this->requestQuery->get('folderId') ?? false) {
            $selectedFolders = [$this->requestQuery->get('folderId')];
        }

        $translator = $this->buildTranslatorWithComments(1);

        /* If there's exactly one recipient, use that User instance as the recipient
         * Otherwise, fall back to default global moderation requirement to determine what call to action is here
         */
        if (1 === $users->count()) {
            $recipient = $this->getUser();
        } else {
            /* Multiple people: just have the publish button I guess?
             */
            $recipient= new User();
        }

        $moment = $this->mapper->createModel()
            ->setRecipient($recipient)
            ->setCreator($this->getUser())
            ->draft();

        // This should follow the same structure as a moment's _links, but it's for creating stuff instead
        $usersString = implode(',', $users->getPropertyValues('id'));
        $globalConfig = (bool)$this->getConfigLoader()->get(ConfigMapper::NAME_LEARNING_MOMENTS_MODERATION_ENABLED);
        $nextRoutes = (new NextRoutes())->generate($moment, $this->getUser(), $globalConfig);

        $createRoutes = [
            'publish' => $nextRoutes['publish'] && $this->getRouteIfAuthorized(
                'learningMomentEvidence.postPublish',
                [ 'userIds' => $usersString, ],
            ),
            'draft'   => $this->getRouteIfAuthorized(
                'learningMomentEvidence.postDraft',
                [ 'userIds' => $usersString, ],
            ),
            'submit'  => $this->getRouteIfAuthorized(
                'learningMomentEvidence.postSubmit',
                [ 'userIds' => $usersString, ],
            ),
        ];

        $params = [
            'hasAutosave'              => $users->count() === 1, // only autosave on create if there's one target user
            'moment'                   => $moment,
            'momentTranslated'         => $translator($moment),
            'editorTemplates'          => $this->editorTemplateHelper->getTemplatesForCategory(),
            'createRoutes'             => $createRoutes,
            'curriculumTaggingEnabled' => $this->curriculumTaggingEnabled(),
            'curriculumNodes'          => [],
            'folders'  => $folders ? $folders->map(fn(Folder\Model $f) => [
                'id' => $f->getId(),
                'title' => $f->getTitle(),
            ])->reindex() : [],
            'selectedFolders'          => $selectedFolders,
            'otherUsers'               => $otherUsers,
            'sharingForSelf'           => $sharingForSelf,
        ];

        $this->response = $this->responseFromTemplate('@learningMomentEvidence/form.twig', $params);
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
    public function postMomentForUsers(Collection $users, ?string $defaultState = null): void
    {
        /* Since this is a collection of users, each of whom might have a unique
         * configuration (e.g. some year levels require moderation, some don't)
         * you'll end up with an $evidenceCollection of mixed results; some will
         * submit and publish immediately whilst some end up in a moderation queue.
         */
        $globalConfig = (bool)$this->getConfigLoader()->get(ConfigMapper::NAME_LEARNING_MOMENTS_MODERATION_ENABLED);
        $moderationRequired = (new ModerationRequired())->isSatisfiedBy($globalConfig, $this->getUser()->getYear());

        $requestBody = $this->request->getParsedBody();

        /* This is a bit brutal and maybe you can mess around more with a diff,
         * but this is guaranteed to be correct without all the kerfuffle...for now
         */
        $folders = $this->folderMapper->findByColumn('id', $requestBody['folders']);

        //unless there are mapper errors (and those will change it to 500 as required
        //set the return code to 201 (Created)

        // Save the evidence
        $momentCollection = $this->mapper->createCollection();
        $baseModel          = (new \Schoolbox\Learning\Moment\Translator\ArrayToModel\MutableFieldsOnly(new CollectorFactory()))($requestBody)
            ->setCreator($this->getUser());

        $tokenReplacer      = new TokenReplacer\Student(new TokenReplacer\Simple());
        foreach ($users as $user) {
            $momentModel = clone $baseModel;
            $momentModel = $momentModel
                ->setRecipient($user)
                ->setBody($tokenReplacer->replace($momentModel->getBody(), $user))
                ->setEvidence(new \Schoolbox\GenericCollection(\Schoolbox\Learning\Moment\Evidence\Model::class));

            // only clone evidence if it exists...
            if (null !== $baseModel->getEvidence()) {
                /* Deep Clone
                 *
                 * This is super painfully extravagant. Surely there's a better way than this.
                 * TODO Maybe implement a magic __clone method inside $baseModel's instance?
                 */
                foreach ($baseModel->getEvidence() as $evidence) {
                    $newEvidence = clone $evidence;

                    /* For some reason this is necessary. The MutableFieldsOnly translator spawns a new Moment instance,
                     * disconnecting it from the relationship contained inside each Evidence instance. This means we have
                     * to map the ids manually, which seems a bit counter-intuitive.
                     */
                    $newEvidence->setMoment($momentModel);

                    $momentModel->getEvidence()->add($newEvidence);
                }
            }

            if ($this->requestPost->get('draft', false)) {
                // autosave draft, save as draft
                $momentModel->draft();
            } else {
                if (null !== $defaultState) {
                    /* Spawn a learning moment in a particular state
                     */
                    $this->updateState($momentModel, $defaultState);
                } elseif ($moderationRequired) {
                    /* Accelerate into the next state based on configs and year level settings
                     */
                    $momentModel->draft();
                } else {
                    /* Does not require moderation; just send it into the world
                     */
                    $momentModel->publish($this->getUser());
                }
            }

            /* Save the set of $folders to each person's learning moment
             */
            $momentModel->setFolders($folders);

            $momentCollection->add($momentModel);
        }

        $this->mapper->save($momentCollection);

        foreach ($momentCollection as $moment) {
            $this->mapper->saveEvidence($moment);
        }

        /**
         * use buildTranslatorWithComments as that contains the _links needed for draft.js
         * where the other translator had excluded _links (for historic reasons)
         */
        $this->response = $this->responseAsJson(
            $momentCollection->map(
                $this->buildTranslatorWithComments(1),
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
        /* TODO rename
         *  Temporary rewrite of misnamed var, but this is linked to the Module implementation
         *  which is potentially going to affect api behaviour, do this gently for now. You'd
         *  have to modify preloadEvidence otherwise, which would affect way more things
         */
        $moment = $evidence;

        /* These are folders that the recipient can see via their membership;
         * Note that a staff member might edit a student's learning moment, so you have to use
         * the recipient's user instance, not the viewer's
         */
        $folders = null;

        /* findAllForUser() disregards the SERVICE_HOMEPAGE filter when combining internal folders
         * with external folders via the sis, allowing for some folders to potentially be revealed
         * that should otherwise have been precluded.
         */
        $userAccesses = $this->userAccessMapper->findAllForUser($moment->getRecipient())
            ->filter(function (UserAccess $access) {
                return UserAccess::SERVICE_HOMEPAGE === $access->getService();
            });

        if ($userAccesses->count()) {
            $folders = $this->folderMapper->findByColumn('id', $userAccesses->getPropertyValues('serviceIdentifier'));
        }

        /* These folder ids were associated with the learning moment
         */
        $selectedFolders = $moment->getFolders()->getPropertyValues('id');

        $translator = $this->buildTranslatorWithComments(1);

        $params = [
            'hasAutosave'              => true, // can always autosave on the modify form (assuming you have draft perms handled by routes)
            'moment'                   => $moment,
            'momentTranslated'         => $translator($moment),
            'editorTemplates'          => $this->editorTemplateHelper->getTemplatesForCategory(),
            'createRoutes'             => null,
            'curriculumTaggingEnabled' => $this->curriculumTaggingEnabled(),
            'curriculumNodes'          => $moment->getViewableCurriculumNodes()?->reindex(),
            'folders'  => $folders ? $folders->map(fn(Folder\Model $f) => [
                'id' => $f->getId(),
                'title' => $f->getTitle(),
            ])->reindex() : [],
            'selectedFolders'          => $selectedFolders,
            'otherUsers'               => [],
            'sharingForSelf'           => $this->getUser() === $moment->getRecipient(),
        ];

        $this->response = $this->responseFromTemplate('@learningMomentEvidence/form.twig', $params);
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
        /* TODO rename
         *  Temporary rewrite of misnamed var, but this is linked to the Module implementation
         *  which is potentially going to affect api behaviour, do this gently for now. You'd
         *  have to modify preloadEvidence otherwise, which would affect way more things
         */
        $moment = $evidence;

        $requestBody  = $this->request->getParsedBody();

        $requestTranslator = new \Schoolbox\Learning\Moment\Translator\ArrayToModel\MutableFieldsOnly(new CollectorFactory());
        $requestTranslator->setCurriculumTaggingEnabled($this->curriculumTaggingEnabled());
        $tokenReplacer  = new TokenReplacer\Student(new TokenReplacer\Simple());
        $moment  = $requestTranslator($requestBody, $moment)
            ->setBody($tokenReplacer->replace($moment->getBody(), $moment->getRecipient()));

        if ($requestBody['draft'] ?? false) {
            // autosave draft, save as draft
            $moment->draft();
        }

        $this->mapper->save($moment);

        /**
         * use buildTranslatorWithComments as that contains the _links needed for draft.js
         * where the other translator had excluded _links (for historic reasons)
         */
        $responseTranslator = $this->buildTranslatorWithComments(1);
        $this->response     = $this->responseAsJson($responseTranslator($moment));
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
        /** @var \Schoolbox\Learning\Moment\Tag\Mapper $tagMapper */
        $tagMapper = $this->mapper->getJoinMapper('moment_tag');
        $tags      = $tagMapper->findAll();
        $output    = [];
        /** @var \Schoolbox\Learning\Moment\Tag\Model $tag */
        foreach ($tags as $tag) {
            $output[] = [
                'id'   => $tag->getId(),
                'name' => $tag->getName(),
            ];
        }
        $this->response = $this->responseAsJson($output);
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
     * Bulk publish a set of $moments
     *
     * @return void
     * @throws Exception
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function putBulkPublish(): void
    {
        $stack = [];

        /* Find all the learning moments to bulk publish
         */
        $requestBody = $this->request->getParsedBody();

        $moments = $this->mapper->findByColumn('id', $requestBody['momentIds']);

        /* Process each moment into the next state individually, collecting a $stack
         * of translated moments in their new state
         */
        foreach ($moments as $moment) {
            $stack[$moment->getId()] = $this->putWithState($moment, Model::STATUS_PUBLISHED);
        }

        $this->response = $this->responseAsJson($stack);
    }


    /**
     * Reject a Moment, along with a reason which goes int the Moment's comment thread.
     *
     * @param Model $evidence
     * @return void
     * @throws Exception
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function putReject(Model $evidence): void
    {
        $this->response = $this->responseAsJson($this->putWithState($evidence, Model::STATUS_REJECTED));
    }


    /**
     * Bulk reject a set of $moments
     *
     * @return void
     * @throws Exception
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function putBulkReject(): void
    {
        $stack = [];

        /* Find all the learning moments to bulk reject
         */
        $requestBody = $this->request->getParsedBody();

        $moments = $this->mapper->findByColumn('id', $requestBody['momentIds']);

        /* Process each moment into the next state individually, collecting a $stack
         * of translated moments in their new state
         */
        foreach ($moments as $moment) {
            $stack[$moment->getId()] = $this->putWithState($moment, Model::STATUS_REJECTED);
        }

        $this->response = $this->responseAsJson($stack);
    }


    /**
     * Place $moment into the new state $state, along with any modifications made to its
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
        /* TODO rename
         *  Temporary rewrite of misnamed var, but this is linked to the Module implementation
         *  which is potentially going to affect api behaviour, do this gently for now. You'd
         *  have to modify preloadEvidence otherwise, which would affect way more things
         */
        $moment = $evidence;

        $requestBody = $this->request->getParsedBody();

        /* This might be empty if submitting directly from the activity feed;
         *
         * It'll be an array of stuff from the Modify form though, at which point we take what was
         * written there and update our model here.
         */
        if (!empty($requestBody)) {
            $requestTranslator = new \Schoolbox\Learning\Moment\Translator\ArrayToModel\MutableFieldsOnly(new CollectorFactory());
            $requestTranslator->setCurriculumTaggingEnabled($this->curriculumTaggingEnabled());
            $tokenReplacer = new TokenReplacer\Student(new TokenReplacer\Simple());

            /* This is a pretty convoluted way of doing things just to decide on a subset of nodes
             * Anyway, this $baseModel contains any curriculum nodes/tags/evidence you might have altered
             */
            $baseModel = (new \Schoolbox\Learning\Moment\Translator\ArrayToModel\MutableFieldsOnly(new CollectorFactory()))($requestBody)
                ->setCreator($this->getUser());

            $moment = $requestTranslator($requestBody, $moment)
                ->setBody($tokenReplacer->replace($baseModel->getBody() ?? $moment->getBody(), $moment->getRecipient()))
                ->setTags($baseModel->getTags() ?? $moment->getTags())
                ->setCurriculumNodes($baseModel->getCurriculumNodes() ?? $moment->getCurriculumNodes());

            // only clone evidence if it exists...
            $moment->setEvidence(new \Schoolbox\GenericCollection(\Schoolbox\Learning\Moment\Evidence\Model::class));
            if (null !== $baseModel->getEvidence()) {
                /* Deep Clone
                 *
                 * This is super painfully extravagant. Surely there's a better way than this.
                 * TODO Maybe implement a magic __clone method inside $baseModel's instance?
                 */
                foreach ($baseModel->getEvidence() as $evidence) {
                    $newEvidence = clone $evidence;

                    /* For some reason this is necessary. The MutableFieldsOnly translator spawns a new Moment instance,
                     * disconnecting it from the relationship contained inside each Evidence instance. This means we have
                     * to map the ids manually, which seems a bit counter-intuitive.
                     */
                    $newEvidence->setMoment($moment);
                    $moment->getEvidence()->add($newEvidence);
                }
            }
        }

        /* Shove it into the new state, $state
         */
        $this->updateState($moment, $state);

        /* This is a bit brutal and maybe you can mess around more with a diff,
         * but this is guaranteed to be correct without all the kerfuffle...for now
         */
        $moment->setFolders($this->folderMapper->findByColumn('id', $requestBody['folders']));

        /* Publish only, Staff only, soft-delete all the comments associated with
         * this learning moment if the user asked to do so
         */
        if (Model::STATUS_PUBLISHED === $state && $this->getUser()->getRole()->isStaff()) {
            if (null !== $moment->getDiscussionThread() && ($requestBody['deleteComments'] ?? false)) {
                $this->commentMapper->deleteByColumn('discussion_thread_id', $moment->getDiscussionThread()->getId());
            }
        }

        /* Reject only, handle adding a rejection reason
         */
        if (Model::STATUS_REJECTED === $state) {
            /* If a discussion thread never existed, spawn one
             */
            $discussionThread = $moment->getDiscussionThread();

            if (null === $discussionThread) {
                $discussionThread = $this->discussionThreadMapper->createModel()
                    ->setOpen(true);

                $this->discussionThreadMapper->save($discussionThread);

                $moment->setDiscussionThread($discussionThread);
            }

            /* Spawn a comment
             * Save it to $moment's discussion thread somehow
             */
            $comment = $this->commentMapper->createModel()
                ->setContent($requestBody['rejectionReason'] ?? '')
                ->setAuthor($this->getUser())
                ->setThread($moment->getDiscussionThread());

            $this->commentMapper->save($comment);
        }


        /* Save it
         */
        $this->mapper->save($moment);
        $this->mapper->saveEvidence($moment);

        /**
         * use buildTranslatorWithComments as that contains the _links needed for draft.js
         * where the other translator had excluded _links (for historic reasons)
         */
        $responseTranslator = $this->buildTranslatorWithComments(1);

        $translated = $responseTranslator($moment);

        /* A shortcut to urls for changing states in this learning moment
         */
        // $translated['_links'] = $this->getNextRoutes($moment);

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
     * Get the cohort report for a given folder
     *
     * @param Folder\Model $folder
     * @return void
     */
    public function getCohortReport(Folder\Model $folder): void
    {
        $this->response = $this->responseFromTemplate(
            '@learningMomentEvidence/cohortReport.twig',
            [ 'folder' => $folder, ],
            sprintf('Cohort Learning Moments - %s', $folder->getName())
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
     * Also applies any additional filters (expressed as GET params)
     *
     * ex.
     * https://vagrant.dev.schoolbox.cloud/learning/evidence/cohort/80/payload?userIds=1,2,3,4&authorIds=13,14,15,16&tagIds=7,8,9&states=draft,submitted,published&from=2025-01-02&to=2025-08-11
     *
     * GET the following struct to this endpoint:
     * [
     *     userIds: [1,2,3,4],
     *     authorIds: [13,14,15,16],
     *     tagIds: [7,8,9],
     *     states: ["draft","submitted","published"],
     *     from: "2025-01-02",
     *     to: "2025-08-11"
     * ]
     *
     * @api GET /learning/evidence/cohort/{folderId}
     * @param Folder\Model $folder
     * @return void
     * @throws RouteNotFound
     */
    public function getCohortReportPayload(Folder\Model $folder): void
    {
        $userIds = $this->requestQuery->get('userIds') ?? [];
        $authorIds = $this->requestQuery->get('authorIds') ?? [];
        $folderIds = $this->requestQuery->get('folderIds') ?? [];
        $tagIds = $this->requestQuery->get('tagIds') ?? [];
        $states = $this->requestQuery->get('states') ?? [];

        $cursor = new CohortReport\Cursor(
            $this->mapper,
            $this->userAccessMapper,
            $folder,
            is_array($userIds) ? $userIds : explode(',', $userIds),
            $this->requestQuery->get('from') ?? null,
            $this->requestQuery->get('to') ?? null,
            is_array($authorIds) ? $authorIds : explode(',', $authorIds),
            is_array($folderIds) ? $folderIds : explode(',', $folderIds),
            is_array($tagIds) ? $tagIds : explode(',', $tagIds),
            is_array($states) ? $states : explode(',', $states),
        );

        $results = $cursor->retrieveResults($this->requestQuery->get('cursor') ?? null);

        /* Augment with hasComments() evaluation and _links derivations
         * Sadly these are deeply coupled to Controllers
         */
        $payload = $this->convert($results);

        $this->response = $this->responseAsJson([
            'data'   => $payload,
            'metadata' => [
                'count'  => $cursor->retrieveResultCount(),
                'cursor' => [
                    'current' => $this->requestQuery->get('cursor') ?? $cursor->current($results),
                    'next'    => $cursor->next($results),
                ],
            ],
        ]);
    }


    /**
     * Convert a fully-hydrated set of $moments into a scalar array that you can nicely
     * json_encode for snake lord
     *
     * @param GenericCollection $moments
     * @return array
     * @throws RouteNotFound
     */
    private function convert(GenericCollection $moments): array
    {
        $payload = [];

        foreach ($moments as $moment) {
            /* Accumulate a list of associated files for this learning moment
             * At the moment there's gonna be just one but SA-20433 causes this to be multiple
             */
            $files = [];

            if (null !== $moment->getEvidence()->first()?->getStorage()) {
                $files[] = call_user_func($this->storageTranslator, $moment->getEvidence()->first()->getStorage());
            }

            /* The actual body may be too long or contain too much HTML (i.e. images/videos)
             * so we can't just show it in the cohort report as-is.
             * Similar to news, provide a cut-down blurb of the body content to show instead
             */
            $blurb = \TextFormatter::cutSentence(
                \Scrub::html2text($moment->getBody()),
                count($files) > 0 ? self::COHORT_REPORT_BLURB_LENGTH : self::COHORT_REPORT_BLURB_LENGTH_EXTENDED,
            );

            /* If the learning moment has a body content, there's something to read
             * (ripped from news land)
             *
             * It might be the same as the blurb, but there are complications with
             * that comparison. The blurb could essentially be a strip_tags of the body,
             * but if the body contained html only (e.g. an image or a video or an iframe)
             * then strip_tags will remove whatever was there; it's valid content that should
             * be seen by users, but has been inadvertently misunderstood by a boolean outcome
             * and we don't have an easy answer to that problem
             *
             * For now, presume there's something to see
             * Show the link
             */
            $showReadMore = '' !== (string)$moment->getBody();

            /* This is a stack because empty payloads result in a datatype difference in frontend world:
             * empty payloads emerge as [], but non-empty payloads emerge as {}
             */
            $userTranslator = new \Schoolbox\User\Translator\ModelToArray();

            // We'll steal the links from here
            // TODO: Let's see how much of this can be refactored to just use the translator directly... I expect most if not all of it
            $translator = $this->buildTranslatorWithComments(1);
            $momentTranslated = $translator($moment);

            $payload[] = [
                'id' => $moment->getId(),
                'body' => \Scrub::html($moment->getBody()),
                'blurb' => $blurb,
                'showReadMore' => $showReadMore,
                'files' => $files,
                /* TODO we usually add role for extra meta about students and staffs, especially in cohorty scenarios
                 *  since there's a higher likelihood of encountering people with same- or same-sounding names.
                 *  Role description helps distinguish them. But it wasn't in any of the designs. I'm expecting
                 *  to need this further down the trail
                 */
                'recipient' => $userTranslator->convertLinkedUser($moment->getRecipient()),
                'creator' => $userTranslator->convertLinkedUser($moment->getCreator()),
                /* This is always set
                 */
                'createdAt' => [
                    'timestamp' => $moment->getCreatedAt()->getUnixTimestamp(),
                    'displayAs' => $moment->getCreatedAt()->getShortDateOnly(),
                    'rfc' => $moment->getCreatedAt()->toRfc3339String(),
                ],
                /* We don't store updatedBy, for whatever reason: I suppose it was presumed
                 * to always be the same as its author...
                 *
                 * Anyway, this is the localised version of config's date_short which you can use
                 * to group moments per day.
                 */
                'updatedAt' => [
                    'timestamp' => $moment->getUpdatedAt()->getUnixTimestamp(),
                    'displayAs' => $moment->getUpdatedAt()->getShortDateOnly(),
                    'rfc' => $moment->getUpdatedAt()->toRfc3339String(),
                ],
                /* Whether this moment had comments or not, which affects a user interaction
                 * where they have an opportunity to delete comments upon approval
                 */
                'hasComments' => $this->hasComments($moment),
                /* The current state of the learning moment
                 */
                'currentState' => $moment->getCurrentState(),
                /* Any associated tags which you can find in a db lookup table
                 */
                'tags' => $this->getTagsForMoment($moment),
                /* Add any associated curriculum nodes
                 * TODO will be added in a future ticket that has yet to be written.
                 */
                'curriculumNodes' => [],
                /* A shortcut to urls for changing states in this learning moment
                 * Note that a controller method must fulfil this node after the fact, for aura reasons
                 * (not easy to dedupe that implementation, sadly)
                 */
                '_links' => $momentTranslated['_links']
            ];
        }

        return $payload;
    }


    /**
     * Convert a set of moment tags into a scalar stack
     *
     * @param Model $moment
     * @return array
     */
    private function getTagsForMoment(Model $moment): array
    {
        $stack = [];

        foreach ($moment->getTags() as $tag) {
            $stack[] = [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
            ];
        }

        return $stack;
    }


    /**
     * Bulk delete a set of $moments
     *
     * @return void
     */
    public function deleteBulkDelete(): void
    {
        /* Find all the learning moments to bulk delete
         */
        $requestBody = $this->request->getParsedBody();

        $this->mapper->deleteByColumn('id', $requestBody['momentIds']);

        $this->response = $this->responseEmpty(204);
    }


    /**
     * Get a list of folders specifically for the cohort report
     * This might be better off in \Schoolbox\Resource\Folder\Controller instead, maybe
     *
     * @return void
     */
    public function getFolderList(): void
    {
        $keyword = $this->requestQuery->get('query') ?? '';
        $stack = [];

        /* No keyword? No trabajo
         */
        if ('' === trim((string)$keyword)) {
            $this->response = $this->responseEmpty(400);
            return;
        }

        /* Find members of your cohort
         */
        $cohortFolderId = $this->requestQuery->get('cohortFolderId') ?? '';

        /* No cohort folder? No trabajo
         */
        if ('' === trim((string)$cohortFolderId)) {
            $this->response = $this->responseEmpty(400);
            return;
        }

        /* Find a set of users who are somehow enrolled in $folder as students,
         * and/or involved in $folder as group members
         *
         * Note that a folder can be both a group and a class-coded folder which derives its
         * members from the Externals
         */
        try {
            $cohortFolder = $this->folderMapper->findById($cohortFolderId);
            $cohortIds = $this->userAccessMapper->findUsersInCategory($cohortFolder, \Schoolbox\Role::STUDENT)->indexBy('id')->keys();
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->response = $this->responseEmpty(400);
            return;
        }

        $dbInternal = $this->folderMapper->getDatabase();

        /* Find the folder ids where your cohort made a learning moment folder relationship
         */
        $cohortPlaceholders = Builder::getArrayPlaceholders($cohortIds, 'cohortIds');
        $params = array_merge(Builder::getArrayParameters($cohortIds, 'cohortIds'));

        $query = "
            SELECT mf.folder_id
            FROM moment_folder mf
            INNER JOIN moment m ON mf.moment_id = m.id
            WHERE m.deleted_at IS NULL
            AND m.user_id IN {$cohortPlaceholders}
        ";

        $result = $this->folderMapper->getDatabase()->executeQuery($query, $params)->fetchAll();

        $folderIds = array_column($result, 'folder_id');

        /* Find those folder instances, only include Groups and Classes in the stack
         */
        if (!\count($folderIds)) {
            $this->response = $this->responseEmpty(400);
            return;
        }

        $pb = $this->folderMapper->getPredicateBuilder()
            ->in('id', $folderIds)
            ->any(
                $this->folderMapper->getPredicateBuilder()
                    ->like("folder.name", "%{$dbInternal->getQuoter()->escapeForLike($keyword)}%")
                    ->like('cc.code', "%{$dbInternal->getQuoter()->escapeForLike($keyword)}%")
            );

        $folders = $this->folderMapper->findByPredicates($pb);

        /** @var \Schoolbox\Resource\Folder\Model $folder */
        foreach ($folders as $folder) {
            /* Neither a group nor a class: skip it
             *
             * You can't tell if a folder -stopped being- a group/class at some point
             * after the learning moment was created: keep filtering these out
             */
            if (!$folder->isGroup() && !$folder->isClass()) {
                continue;
            }

            $stack[] = [
                'id' => $folder->getId(),
                'name' => $folder->getNameWithCodes()
            ];
        }

        $this->response = $this->responseAsJson($stack);
    }
}
