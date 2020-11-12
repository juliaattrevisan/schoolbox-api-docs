<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Translator;

use Alaress_Date_Format;
use Schoolbox\Discussion\Thread\Model as DiscussionThread;
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
class ArrayToModel
{
    /**
     * @var CollectorFactory
     */
    private $collectorFactory;


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
     * @param array               $json
     * @param Evidence\Model|null $model If provided, update this model;
     *                                   otherwise create a new one
     * @return Evidence\Model
     * @throws ResourceException if the collector factory can't find the file
     *                           that was uploaded
     */
    public function __invoke(array $json, ?Evidence\Model $model = null): Evidence\Model
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
//        if (array_key_exists('deletedAt', $json)) {
//            $model->setDeletedAt(new Alaress_Date_Format($json['deletedAt'], DATE_ATOM));
//        }
        if (array_key_exists('body', $json)) {
            $model->setBody($json['body']);
        }

        if (array_key_exists('creator', $json)) {
            if (isset($json['creator'])) {
                if (is_array($json['creator']) && array_key_exists('id', $json['creator'])) {
                    $model->setCreator((new User())->setId($json['creator']['id']));
                }
            } else {
                $model->setCreator(null);
            }
        }

        if (isset($json['discussionThread'])
            && is_array($json['discussionThread'])
            && array_key_exists('id', $json['discussionThread'])
        ) {
            // Expect a discussion thread
            $model->setDiscussionThread((new DiscussionThread())->setId($json['discussionThread']['id']));
        }
        if (isset($json['recipient'])
            && is_array($json['recipient'])
            && array_key_exists('id', $json['recipient'])
        ) {
            // Expect a user object
            $model->setRecipient((new User())->setId($json['recipient']['id']));
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
                if (is_array($json['storage'])
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

        return $model;
    }
}
