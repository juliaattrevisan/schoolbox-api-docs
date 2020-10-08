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
     * @param array $json
     * @return Evidence\Model
     */
    public function __invoke(array $json): Evidence\Model
    {
        $model = new Evidence\Model();


        // It feels like this should be possible via the mapper.
        // I'll test that out, but that does mean the database and JSON
        // representations are tied together.
//        $mapper = Evidence\Mapper::getInstance();
//        $mapper->hydrate($json);


        // Only set fields which are a part of the json payload
        if (isset($json['id'])) {
            $model->setId($json['id']);
        }
        if (isset($json['createdAt'])) {
            // Expect a datetime object
            $model->setCreatedAt(new Alaress_Date_Format($json['createdAt'], DATE_ATOM));
        }
        if (isset($json['updatedAt'])) {
            $model->setUpdatedAt(new Alaress_Date_Format($json['updatedAt'], DATE_ATOM));
        }
//        if (isset($json['deletedAt'])) {
//            $model->setDeletedAt(new Alaress_Date_Format($json['deletedAt'], DATE_ATOM));
//        }
        if (isset($json['body'])) {
            $model->setBody($json['body']);
        }
        if (isset($json['creator']['id'])) {
            // Expect a user ID, but no other details yet
            $model->setCreator((new User())->setId($json['creator']['id']));
        }
        if (isset($json['discussionThread']['id'])) {
            // Expect a discussion thread
            $model->setDiscussionThread((new DiscussionThread())->setId($json['discussionThread']['id']));
        }
        if (isset($json['filename'])) {
            $model->setFilename($json['filename']);
        }
        if (isset($json['recipient']['id'])) {
            // Expect a user object
            $model->setRecipient((new User())->setId($json['recipient']['id']));
        }
        if (isset($json['storage']['hash'])) {
            // Expect a hash? Not sure how storage files should be represented
            // in API requests/responses yet
            $model->setStorage((new StorageModel())->setHash($json['storage']['hash']));
        }

        return $model;
    }
}
