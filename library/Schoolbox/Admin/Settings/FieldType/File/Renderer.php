<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\File;

use Schoolbox\Admin\Settings\FieldType\AbstractRenderer;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Attachment\Zone;

class Renderer extends AbstractRenderer
{

    /**
     * @inheritdoc
     */
    protected function getTemplate(): string
    {
        return 'file.twig';
    }


    /**
     * @inheritdoc
     * @throws \UnexpectedValueException
     */
    protected function getData(Config $config): array
    {
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
        $this->attachFiles($config, $zone);

        return \array_merge(parent::getData($config), [
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


    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_FILE;
    }
}
