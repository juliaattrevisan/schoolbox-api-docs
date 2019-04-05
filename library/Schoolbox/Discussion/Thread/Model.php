<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

use Alaress\Json;
use Schoolbox\ISoftDeletable;
use Schoolbox\Json\Api\AuraLink;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

/**
 * Model for discussion threads.
 */
class Model implements ISoftDeletable, Json\Serializable, Json\Linkable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
    use Json\SerializeTrait;


    /**
     * @var bool
     */
    private $open;


    /**
     * @return bool
     */
    public function getOpen(): ?bool
    {
        return $this->open;
    }


    /**
     * @param bool $open
     * @return static
     */
    public function setOpen(bool $open): self
    {
        $this->open = $open;
        return $this;
    }


    /**
     * @return array
     */
    public function getDefaultJsonMap(): array
    {
        $map = [
            'id',
            'open',
            'createdAt',
            'updatedAt',
        ];
        return $map;
    }


    /**
     * @return array
     */
    public function getJsonApiLinks(): array
    {
        // TODO figure out how to create contextual links
        return [
//            'getComments' => new AuraLink(
//                'discussionThreads.getCommentsByThread',
//                ['id' => $this->id, 'thread' => $this,]
//            ),
//            'createComment' => new AuraLink(
//                'discussionThreads.createCommentInThread',
//                ['id' => $this->id, 'thread' => $this,]
//            ),
        ];
    }
}
