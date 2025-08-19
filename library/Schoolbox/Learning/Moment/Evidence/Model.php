<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\User;

class Model implements IFile
{
    use StorableTrait;
    use ModelTrait\CreatedAtDate;

    /**
     * The types of evidence
     */
    public const MOMENT_TYPE_TEXT   = 'text';
    public const MOMENT_TYPE_UPLOAD = 'upload';
    public const MOMENT_TYPE_PHOTO  = 'photo';
    public const MOMENT_TYPE_AUDIO  = 'audio';
    public const MOMENT_TYPE_VIDEO  = 'video';


    /**
     * @var \Schoolbox\Learning\Moment\Model|null
     */
    private ?\Schoolbox\Learning\Moment\Model $moment = null;


    /**
     * The type of evidence: photo/text/upload/video/audio
     *
     * @var string|null
     */
    private ?string $type = null;


    /**
     * @return User
     */
    public function getOwner(): User
    {
        return $this->moment->getCreator();
    }


    /**
     * @return \Schoolbox\Learning\Moment\Model|null
     */
    public function getMoment(): ?\Schoolbox\Learning\Moment\Model
    {
        return $this->moment;
    }


    /**
     * @param \Schoolbox\Learning\Moment\Model|null $moment
     * @return $this
     */
    public function setMoment(?\Schoolbox\Learning\Moment\Model $moment): self
    {
        $this->moment = $moment;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }


    /**
     * @param string|null $type
     * @return $this
     */
    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }
}
