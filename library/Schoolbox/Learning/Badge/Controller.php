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
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
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
     * @var Award\Mapper
     */
    private Award\Mapper $awardMapper;


    /**
     * Constructor
     *
     * @param Mapper       $mapper
     * @param Award\Mapper $awardMapper
     */
    public function __construct(Mapper $mapper, Award\Mapper $awardMapper)
    {
        $this->mapper = $mapper;
        $this->awardMapper = $awardMapper;
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
     * Get a list of badges and their counts, awarded to $user
     *
     * @api GET /learning/badge/{userId}/awarded
     *
     * @param User $user
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getAwardedBadges(User $user): array
    {
        $awards = $this->awardMapper->findRecentForUser($user);

        $stack = [];

        /* Award translator; uses a Badge translator to convert one of
         * the parts into a fully-fledged badge array representation
         */
        $translator = new Award\Translator\ModelToArray(
            new User\Translator\ModelToArray(),
            new Translator\ModelToArray(
                new User\Translator\ModelToArray(),
                new Storage\Translator\ModelToArray(),
                new ScrubAdapter()
            )
        );

        /* Group by category
         */
        foreach ($awards as $award) {
            $badgeId = $award->getBadge()->getId();
            $categoryId = $award->getBadge()->getCategory()->getId();
            $badge = $translator($award);

            /* Init stack
             *
             * Remember one copy of the category
             */
            if (!isset($stack[$categoryId])) {
                $stack[$categoryId] = $badge['badge']['category'];
                $stack[$categoryId]['awards'] = [];
            }

            /* Init badge count
             *
             * findRecentForUser() is listed reverse chronologically so the first
             * in the list is the most recently awarded
             */
            if (!isset($stack[$categoryId]['awards'][$badgeId])) {

                /* This is a set-based aggregation, not an individual model property
                 */
                $badge['count'] = 0;

                /* Stick it into the stack
                 */
                $stack[$categoryId]['awards'][$badgeId] = $badge;
            }

            /* Collapse duplicate badge ids with a count
             */
            $stack[$categoryId]['awards'][$badgeId]['count']++;
        }

        return $stack;
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
                    'attachzone'               => $attachzone->getTwigContext(),
                    'categories'               => Category\Mapper::getInstance()->findAll(),
                    'curriculumTaggingEnabled' => $this->getConfigLoader()->get('curriculum_tagging_enabled'),
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
        $translator = new Translator\ArrayToModel\MutableFieldsOnly(new CollectorFactory());
        $model      = $translator($data)
            ->setCreatedBy($this->getUser())
            ->setUpdatedBy($this->getUser());

        // Is curriculum tagging disabled?
        // Set an empty collection if so, as that is required to save the badge
        if (!$this->getConfigLoader()->get('curriculum_tagging_enabled')) {
            $model = $model->setCurriculumNodes(\Schoolbox\Curriculum\Node\Mapper::getInstance()->createCollection());
        }

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
