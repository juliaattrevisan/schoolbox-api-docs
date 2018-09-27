<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\Renderer;

use Schoolbox\Config\Config;
use Schoolbox\Resource\Attachment\Zone;

class File extends Base
{

    /**
     * @inheritdoc
     */
    public function getTemplate(): string
    {
        return 'file.twig';
    }


    /**
     * @return array
     * @throws \UnexpectedValueException
     */
    public function getData(): array
    {
        $config     = $this->getConfig();
        $mimeFilter = (strpos($config->getName(), '_logo') || strpos($config->getName(), '_bg')) ? 'image/*' : '*/*';

        $zone = new Zone($config->getName());
        $zone->setThumbnailSize(64);
        $zone->setAttachPrompt('Attach ' . strtolower($config->getTitle()));
        $zone->setAttachmentsLabel('');
        if ($this->isMulti()) {
            $zone->setMulti(true);
        }
        $zone->setFileVar($this->getFileVar($config));
        $zone->setMimeFilter($mimeFilter);
        $zone->setModalTitle('Select ' . strtolower($config->getTitle()));
        if ($config->getValue()) {
            $zone->attachFiles([$config->getValue()]);
        }

        return \array_merge(parent::getData(), [
            'attachzone' => $zone->getTwigContext(),
        ]);
    }


    /**
     * Returns whether the zone should support one or many files
     *
     * @return bool
     */
    protected function isMulti(): bool
    {
        return false;
    }


    /**
     * @param Config $config
     * @return string
     */
    protected function getFileVar(Config $config): string
    {
        return $config->getName();
    }


    /**
     * Attaches any currently configured files to the zone
     *
     * @param Config $config
     * @param Zone   $zone
     */
    protected function attachFiles(Config $config, Zone $zone): void
    {
        if ($config->getValue()) {
            $zone->attachFiles([$config->getValue()]);
        }
    }
}
