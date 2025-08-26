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
     */
    public function __construct(
        private \Schoolbox\Learning\Moment\Mapper $mapper,
        private Comment\Mapper $commentMapper,
        private EditorTemplateHelper $editorTemplateHelper,
        private \Schoolbox\Permission\Access\UserAccessMapper $userAccessMapper,
        private Storage\Translator\ModelToArray $storageTranslator,
        private \Schoolbox\Resource\Folder\Mapper $folderMapper
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

        // Which variant is being shown?
        // * text
        // * upload
        $momentType = $this->requestQuery->get('momentType') ?: Evidence\Model::MOMENT_TYPE_TEXT;
        $template   = $this->getTemplateFromType($momentType);

        /* These are folders that the recipient can see via their membership;
         * Note that a staff member might edit a student's learning moment, so you have to use
         * the recipient's user instance, not the viewer's
         */
        $folders = null;

        /* This is usually empty when creating, except when you're creating by the class list;
         * that will have a folder already chosen
         */
        $selectedFolders = [];

        /* Students get their learning moment moderation requirement tested;
         * parents and teachers bypass this check
         */
        $moderationRequired = false;

        if ($this->getUser()->getRole()->isStudent()) {
            /* Students crafting learning moments for themselves might be subject to moderation
             */
            $moderationRequired = (new ModerationRequired())->isSatisfiedBy($this->getConfigLoader()->get(ConfigMapper::NAME_LEARNING_MOMENTS_MODERATION_ENABLED), $this->getUser()->getYear());

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

            /* If folderId exists in the querystring params, use that to preselect
             * (you probably came here via the homepage component)
             */
            if ($this->requestQuery->get('folderId') ?? false) {
                $selectedFolders = [$this->requestQuery->get('folderId')];
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
            if ($this->getUser()->getRole()->isParent()) {
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

                /* Parents don't get to associate folders with a learning moment
                 */
            }

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

                    if ($this->requestQuery->get('folderId') ?? false) {
                        $selectedFolders = [$this->requestQuery->get('folderId')];
                    }
                }
            }
        } elseif ($this->getUser()->getRole()->isStaff()) {
            /* If the current user is a staff and the list of recipients is more than one,
             * presume folder involvement to be specifically the Class List from whence you came
             * Request a collection of folders for data struct synonymity
             */
            $folders = $this->folderMapper->findByColumn('id', $this->requestQuery->get('folderId'));

            if ($this->requestQuery->get('folderId') ?? false) {
                $selectedFolders = [$this->requestQuery->get('folderId')];
            }
        }

        $translator = $this->buildTranslatorWithComments(1);
        $moment = $this->mapper->createModel()
            ->setRecipient(new User())
            ->setCreator($this->getUser());

        // This should follow the same structure as a moment's _links, but it's for creating stuff instead
        $usersString = implode(',', $users->getPropertyValues('id'));
        $createRoutes = [
            'publish' => $this->getRouteIfAuthorized(
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
            // TODO: Just swap these out of payload once we're done with the twig file...
            'payload' => [
                'hasAutosave'              => $users->count() === 1, // only autosave on create if there's one target user
                'evidence' => $translator($moment),
                'createRoutes'         => $createRoutes,
                'curriculumTaggingEnabled' => $this->curriculumTaggingEnabled(),
                'curriculumNodes'          => [],
                'folders'  => $folders ? $folders->map(fn(Folder\Model $f) => [
                    'id' => $f->getId(),
                    'title' => $f->getTitle(),
                ])->reindex() : [],
                'selectedFolders' => $selectedFolders,
            ],
            'evidence'                 => $moment,
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
            /* Folder relationships
             */
            'folders' => $folders,
            'selectedFolders' => $selectedFolders,
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

        /* TODO massive assumption atm
         *  This was predicated on moments having exactly one file associated with it,
         *  but now there can be many files associated with it. Display the first
         *  evidence by default until a user changes it, I guess?
         */

        /* Each moment has a body of text that always exists. The default usage here is MOMENT_TYPE_TEXT.
         *
         * Additionally, a learning moment comes with an optional piece(s) of evidence with a particular
         * type which are defined in Evidence\Model. These can be photo/video/audio/upload for now.
         * In the absence of any evidence, we presume text-only, which is MOMENT_TYPE_TEXT.
         */
        $momentType = $moment->getEvidence()->first()?->getType() ?? Evidence\Model::MOMENT_TYPE_TEXT;
        $template   = $this->getTemplateFromType($momentType);

        /* Attachzone stuff for existing attachments
         */
        $zone = $this->buildAttachzone($momentType);
        if ($moment->getEvidence()->first()?->getStorage()) {
            $zone->attachFiles([$moment->getEvidence()->first()->getStorage()->getHash()]);
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
            // TODO: Just swap these out of payload once we're done with the twig file...
            'payload' => [
                'hasAutosave'              => true, // can always autosave on the modify form (assuming you have draft perms handled by routes)
                'evidence'                 => $translator($moment),
                'createRoutes'             => null,
                'curriculumTaggingEnabled' => $this->curriculumTaggingEnabled(),
                'curriculumNodes'          => $moment->getViewableCurriculumNodes()?->reindex(),
                'folders'  => $folders ? $folders->map(fn(Folder\Model $f) => [
                    'id' => $f->getId(),
                    'title' => $f->getTitle(),
                ])->reindex() : [],
                'selectedFolders' => $selectedFolders,
            ],
            'evidence'                 => $moment,
            'users'                    => $moment->getRecipient(),
            'userIds'                  => $moment->getRecipient()->getId(),
            'otherUsers'               => [],
            'sharingForSelf'           => $this->getUser() === $moment->getRecipient(),
            'curriculumTaggingEnabled' => $this->curriculumTaggingEnabled(),
            'moderationRequired'       => $moderationRequired,
            'isCreate'                 => false,
            'momentType'               => $momentType,
            'curriculumCodes'          => $moment->getViewableCurriculumNodes()?->reindex(),
            'zone'                     => $zone->getTwigContext(),
            'hasComments'              => $this->hasComments($moment),
            'returnUrl'                => $this->requestQuery->get('returnUrl'),
            'editorTemplates'          => $this->editorTemplateHelper->getTemplatesForCategory(),
            /* Folder relationships
             */
            'folders' => $folders,
            'selectedFolders' => $selectedFolders,
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
            case Evidence\Model::MOMENT_TYPE_PHOTO:
                $zone->setAttachPrompt('take a picture')
                     ->setIconClass('icon-camera')
                     ->setUseModal(false)
                     ->setCapture(true)
                     ->setMimeFilter('image/*');
                break;
            case Evidence\Model::MOMENT_TYPE_AUDIO:
                $zone->setAttachPrompt('record audio')
                     ->setIconClass('icon-audio')
                     ->setUseModal(false)
                     ->setCapture(true)
                     ->setMimeFilter('audio/*');
                break;
            case Evidence\Model::MOMENT_TYPE_VIDEO:
                $zone->setAttachPrompt('record video')
                     ->setIconClass('icon-video')
                     ->setUseModal(false)
                     ->setCapture(true)
                     ->setMimeFilter('video/*');
                break;
            case Evidence\Model::MOMENT_TYPE_UPLOAD:
            case Evidence\Model::MOMENT_TYPE_TEXT:
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
            case Evidence\Model::MOMENT_TYPE_PHOTO:
                return '@learningMomentEvidence/form/photo.twig';
            case Evidence\Model::MOMENT_TYPE_AUDIO:
                return '@learningMomentEvidence/form/audio.twig';
            case Evidence\Model::MOMENT_TYPE_VIDEO:
                return '@learningMomentEvidence/form/video.twig';
            case Evidence\Model::MOMENT_TYPE_UPLOAD:
                return '@learningMomentEvidence/form/upload.twig';
            case Evidence\Model::MOMENT_TYPE_TEXT:
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
            $moment = $requestTranslator($requestBody, $moment)->setBody($tokenReplacer->replace($moment->getBody(), $moment->getRecipient()));
        }

        /* Shove it into the new state, $state
         */
        $this->updateState($moment, $state);

        /* This is a bit brutal and maybe you can mess around more with a diff,
         * but this is guaranteed to be correct without all the kerfuffle...for now
         */
        $moment->setFolders($this->folderMapper->findByColumn('id', $requestBody['folders']));

        /* Save it
         */
        $this->mapper->save($moment);

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
                '_links' => $this->getNextRoutes($moment),
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
     * For a particular learning moment, decide what actions could happen next.
     *
     * @param Model $moment
     * @return array
     * @throws RouteNotFound
     */
    private function getNextRoutes(Model $moment): array
    {
        $links = (new NextRoutes())->generate($moment, $this->getUser(), $this->getConfigLoader()->get(ConfigMapper::NAME_LEARNING_MOMENTS_MODERATION_ENABLED));

        /* Only get the routes you need
         */
        if ($links['submit']) {
            $links['submit'] = $this->getRouteIfAuthorized('learningMomentEvidence.putSubmit', [
                'evidenceId' => $moment->getId(),
                'creatorId' => $moment->getCreator()->getId(),
                'userId' => $moment->getRecipient()->getId(),
            ]);
        }

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

        /* non-state dependent actions */
        $links['modifyForm'] = $this->getRouteIfAuthorized('learningMomentEvidence.getModifyForm', [
            'evidenceId' => $moment->getId(),
            'creatorId'  => $moment->getCreator()->getId(),
            'userId'     => $moment->getRecipient()->getId(),
        ]);

        $links['delete'] = $this->getRouteIfAuthorized('learningMomentEvidence.deleteEvidence', [
            'evidenceId' => $moment->getId(),
            'creatorId'  => $moment->getCreator()->getId(),
        ]);

        return $links;
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
