<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\SingleFile;

use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

/**
 * Mapper for files attached to a submission box (due work).
 */
class Mapper extends BaseMapper
{
    protected function mapping()
    {
        $this->hasTable('submission_box_file');
        $this->hasModel(Model::class);

        $this->joins('box_id', SubmissionBoxMapper::class, 'id', null, null, 'box', 'box');
        $this->joins('hash', StorageMapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('realname', self::TYPE_STRING, null, 'filename');
    }
}
