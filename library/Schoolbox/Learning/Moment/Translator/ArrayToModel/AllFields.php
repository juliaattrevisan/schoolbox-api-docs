<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Translator\ArrayToModel;

use Alaress_Date_Format;
use InvalidArgumentException;
use Schoolbox\API\Translator\ArrayToModel\Base;
use Schoolbox\Curriculum\Node\Model as CurriculumNode;
use Schoolbox\Discussion\Thread\Model as DiscussionThread;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\Resource\Storage\Model as StorageModel;
use Schoolbox\User;

/**
 * Translator for translating an array representation of learning moment
 * evidence into a model.
 *
 * Used when translating JSON passed in a request body into a model, so that it
 * may be persisted.
 */
class AllFields extends Base
{
    /**
     * @var CollectorFactory
     */
    private CollectorFactory $collectorFactory;


    /**
     * Constructor
     *
     * @param CollectorFactory $collectorFactory
     */
    public function __construct(CollectorFactory $collectorFactory)
    {
        $this->collectorFactory = $collectorFactory;
    }


    /**
     * Translate the array representation of the evidence into a model.
     *
     * @param array                      $json
     * @param object|\Schoolbox\Learning\Moment\Model|null $model If provided, update this model;
     *                                   otherwise create a new one
     * @return object|\Schoolbox\Learning\Moment\Model
     * @throws ResourceException        if the collector factory can't find the
     *                                  file that was uploaded
     * @throws InvalidArgumentException if attempting to set creator or
     *                                  recipient to null
     */
    public function __invoke(array $json, ?object $model = null): object
    {
        if (!isset($model)) {
            $model = (new \Schoolbox\Learning\Moment\Model());
        }


        // It feels like this should be possible via the mapper.
        // I'll test that out, but that does mean the database and JSON
        // representations are tied together.
//        $mapper = Evidence\Mapper::getInstance();
//        $mapper->hydrate($json);


        // Only set fields which are a part of the json payload
        if (array_key_exists('id', $json)) {
            $model->setId($json['id']);
        }
        if (array_key_exists('createdAt', $json)) {
            // Expect a datetime object
            $model->setCreatedAt(
                isset($json['createdAt'])
                    ? new Alaress_Date_Format($json['createdAt'], DATE_ATOM)
                    : null
            );
        }
        if (array_key_exists('updatedAt', $json)) {
            $model->setUpdatedAt(
                isset($json['updatedAt'])
                    ? new Alaress_Date_Format($json['updatedAt'], DATE_ATOM)
                    : null
            );
        }

        // Allow this translator to set deleted_at:
        // this translator is not intended to restrict the fields which may be
        // set, only to create a new instance of an object from JSON.
        if (array_key_exists('deletedAt', $json)) {
            $model->setDeletedAt(
                isset($json['deletedAt'])
                    ? new Alaress_Date_Format($json['deletedAt'], DATE_ATOM)
                    : null
            );
        }

        if (array_key_exists('body', $json)) {
            $model->setBody($json['body']);
        }

        if (array_key_exists('creator', $json)) {
            if (isset($json['creator'])) {
                if (is_array($json['creator']) && array_key_exists('id', $json['creator'])) {
                    $model->setCreator((new User())->setId($json['creator']['id']));
                }
            } else {
                throw new InvalidArgumentException('Attempting to set evidence creator to invalid null value');
            }
        }

        if (array_key_exists('discussionThread', $json)) {
            if (isset($json['discussionThread'])) {
                if (is_array($json['discussionThread']) && array_key_exists('id', $json['discussionThread'])) {
                    $model->setDiscussionThread((new DiscussionThread())->setId($json['discussionThread']['id']));
                }
            } else {
                $model->setDiscussionThread(null);
            }
        }

        if (array_key_exists('recipient', $json)) {
            if (isset($json['recipient'])) {
                if (is_array($json['recipient']) && array_key_exists('id', $json['recipient'])) {
                    $model->setRecipient((new User())->setId($json['recipient']['id']));
                }
            } else {
                throw new InvalidArgumentException('Attempting to set evidence recipient to invalid null value');
            }
        }


        // File upload may be either:
        // * an extant storage hash
        // * a triplet passed from a resource attacher, to be transformed into a
        //   storage hash by the collector factory
        if (array_key_exists('evidence', $json)) {
            if (isset($json['evidence'])) {
                if (is_array($json['evidence'])) {
                    foreach ($json['evidence'] as $evidenceStruct) {
                        $evidence = null;

                        // File upload may be either:
                        // * an extant storage hash
                        // * a triplet passed from a resource attacher, to be transformed into a
                        //   storage hash by the collector factory
                        if (isset($evidenceStruct['file']) && '' !== $evidenceStruct['file']) {
                            $file = $this->collectorFactory->build([$evidenceStruct['file']])->getFirst();
                            if ($file) {
                                $evidence = new \Schoolbox\Learning\Moment\Evidence\Model();

                                $evidence->setMoment($model)
                                    ->setStorage($file)
                                    ->setFilename($file->getBasename());

                                if (null === $model->getEvidence()) {
                                    $model->setEvidence((new \Schoolbox\GenericCollection(\Schoolbox\Learning\Moment\Evidence\Model::class)));
                                }
                            }
                        } else {
                            /* No hash? No trabajo
                             */
                            if (!array_key_exists('hash', $evidenceStruct)) {
                                continue;
                            }

                            $evidence = (new \Schoolbox\Learning\Moment\Evidence\Model())
                                ->setMoment($model);

                            if (array_key_exists('filename', $evidenceStruct)) {
                                $evidence->setFilename($evidenceStruct['filename']);
                            }

                            if (isset($evidenceStruct['hash'])) {
                                $storage = (new StorageModel())->setHash($evidenceStruct['hash']);

                                $evidence->setStorage($storage);
                            }

                            if (null === $model->getEvidence()) {
                                $model->setEvidence((new \Schoolbox\GenericCollection(\Schoolbox\Learning\Moment\Evidence\Model::class)));
                            }
                        }

                        // You can only have up to 10 evidence
                        if ($evidence && $model->getEvidence()->count() < \Schoolbox\Learning\Moment\Controller::MOMENT_MAX_EVIDENCE) {
                            $model->getEvidence()->add($evidence);
                        }
                    }
                }
            }
        }

        // Set folders
        if (array_key_exists('folders', $json)) {
            /* Only try to compile if there are actual values in here, not just an empty set
             */
            if (is_array($json['folders']) && isset($json['folders'][0])) {
                /* The first element could potentially be a csv (and the only element) which actually represents
                 * multiple folders. Curric nodes have this behaviour but not sure when/why, but stands to reason
                 * folders are susceptible to the same phenomenon.
                 *
                 * Somehow this ws related to https://schoolbox.atlassian.net/browse/SA-14113 but no details exist.
                 */
                if (is_string($json['folders'][0]) && str_contains(',', $json['folders'][0])) {
                    /* If it's a csv, snap it apart; otherwise, it's some other string of some kind; can't process it.
                     * Don't do anything in that case.
                     */
                    $json['folders'] = explode(',', $json['folders'][0]);
                }

                /* This only ever sets one level of hydration, but curric nodes and tags depends on it
                 */
                $this->setCollection(
                    $json,
                    'folders',
                    'id',
                    $model,
                    'setFolders',
                    new GenericCollection(\Schoolbox\Resource\Folder\Model::class),
                    new \Schoolbox\Resource\Folder\Model(),
                    'setId'
                );

                /* Kind of repair this after the fact
                 */
                $lookup = [];

                foreach ($json['folders'] as $row) {
                    $lookup[$row['id']] = $row;
                }

                /** @var \Schoolbox\Resource\Folder\Model $folder */
                foreach ($model->getFolders() as $folder) {
                    $folder->setName($lookup[$folder->getId()]['name']);

                    /* Init folder code collection for each folder instance
                     */
                    if (null === $folder->getCodes()) {
                        $folder->setCodes(
                            (new \Schoolbox\GenericCollection(\Schoolbox\Resource\Folder\Code\Model::class))
                        );
                    }

                    $codes = explode(',', $lookup[$folder->getId()]['code']);

                    foreach ($codes as $code) {
                        $folder->getCodes()->add(
                            (new \Schoolbox\Resource\Folder\Code\Model())
                                ->setCode($code)
                        );
                    }
                }
            }
        }

        // Set curriculum nodes
        if (array_key_exists('curriculumNodes', $json)) {
            //try to not break things...
            //if there are no curriculum codes specified (empty array) then just ignore this
            if (is_array($json['curriculumNodes']) && isset($json['curriculumNodes'][0])) {
                //if $json['curriculumNodes'] contains a ',' then explode, as its csv, else believe its just an array
                if (substr_count($json['curriculumNodes'][0], ',')) {
                    $json['curriculumNodes'] = explode(',', $json['curriculumNodes'][0]);
                }
            }
            $this->setCollection(
                $json,
                'curriculumNodes',
                '',
                $model,
                'setCurriculumNodes',
                new GenericCollection(CurriculumNode::class),
                new CurriculumNode(),
                'setId'
            );
        }

        // Set curriculum nodes
        if (array_key_exists('tags', $json)) {
            $this->setCollection(
                $json,
                'tags',
                'id',
                $model,
                'setTags',
                new GenericCollection(\Schoolbox\Learning\Moment\Tag\Model::class),
                new \Schoolbox\Learning\Moment\Tag\Model(),
                'setId'
            );
        }

        return $model;
    }
}
