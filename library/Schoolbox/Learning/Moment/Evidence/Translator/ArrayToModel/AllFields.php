<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Translator\ArrayToModel;

use Alaress_Date_Format;
use Schoolbox\API\Translator\ArrayToModel\Base;
use Schoolbox\Curriculum\Node\Model as CurriculumNode;
use Schoolbox\Discussion\Thread\Model as DiscussionThread;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Moment\Evidence;
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
     * @param object|Evidence\Model|null $model If provided, update this model;
     *                                   otherwise create a new one
     * @return object|Evidence\Model
     * @throws ResourceException if the collector factory can't find the file
     *                                   that was uploaded
     */
    public function __invoke(array $json, ?object $model = null): object
    {
        if (!isset($model)) {
            $model = new Evidence\Model();
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
                // raise a warning, but continue
                trigger_error(
                    'Attempting to set evidence creator to invalid null value: skipping',
                    E_USER_WARNING
                );
            }
        }

        if (array_key_exists('type', $json)) {
            $model->setType($json['type']);
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
                trigger_error(
                    'Attempting to set evidence recipient to invalid null value: skipping',
                    E_USER_WARNING
                );
            }
        }


        // File upload may be either:
        // * an extant storage hash
        // * a triplet passed from a resource attacher, to be transformed into a
        //   storage hash by the collector factory
        if (array_key_exists('file', $json)) {
            if (!isset($json['file'])) {
                $model
                    ->setFilename(null)
                    ->setStorage(null);
            } else {
                $file = $this->collectorFactory->build([$json['file']])->getFirst();
                if ($file) {
                    $model
                        ->setFilename($file->getBasename())
                        ->setStorage($file);
                }
            }
        }


        if (array_key_exists('storage', $json)) {
            if (isset($json['storage'])) {
                if (
                    is_array($json['storage'])
                    && array_key_exists('hash', $json['storage'])
                ) {
                    if (!isset($json['storage']['hash'])) {
                        $model->setStorage(null);
                    } else {
                        $model->setStorage((new StorageModel())->setHash($json['storage']['hash']));
                    }
                }
            } else {
                $model->setStorage(null);
            }
        }
        if (array_key_exists('filename', $json)) {
            $model->setFilename($json['filename']);
        }

        // Set curriculum nodes
        if (array_key_exists('curriculumNodes', $json)) {
            //try to not break things...
            //if $json['curriculumNodes'] contains a ',' then explode, as its csv, else believe its just an array
            if (substr_count($json['curriculumNodes'][0], ',')) {
                $json['curriculumNodes'] = explode(',', $json['curriculumNodes'][0]);
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
                new GenericCollection(Evidence\Tag\Model::class),
                new Evidence\Tag\Model(),
                'setId'
            );
        }

        return $model;
    }
}
