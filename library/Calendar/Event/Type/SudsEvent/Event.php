<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\SudsEvent;

use Calendar\Event\Type\Day;

/**
 * This data is rendered by a Renderer class to display the data
 * in a variety of formats
 */
class Event extends Day\Event
{

    /**
     * @var string|null
     */
    private ?string $code;


    /**
     * @var \Schoolbox\Resource\Folder\Model|null
     */
    private ?\Schoolbox\Resource\Folder\Model $folder;


    /**
     * @return string[]
     */
    protected function getRenderClasses(): array
    {
        return [
            self::RENDER_FORMAT_JSON => Renderer\Javascript::class,
            self::RENDER_FORMAT_VCAL => Renderer\VObject::class,
            self::RENDER_FORMAT_CSV  => Renderer\Csv::class,
        ];
    }


    /**
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->code;
    }


    /**
     * @param string|null $code
     * @return Event
     */
    public function setCode(?string $code): self
    {
        $this->code = $code;
        return $this;
    }


    /**
     * @return \Schoolbox\Resource\Folder\Model|null
     */
    public function getFolder(): ?\Schoolbox\Resource\Folder\Model
    {
        return $this->folder;
    }


    /**
     * @param \Schoolbox\Resource\Folder\Model|null $folder
     * @return Event
     */
    public function setFolder(?\Schoolbox\Resource\Folder\Model $folder): self
    {
        $this->folder = $folder;
        return $this;
    }
}
