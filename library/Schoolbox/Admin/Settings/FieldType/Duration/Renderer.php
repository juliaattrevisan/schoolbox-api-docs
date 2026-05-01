<?php

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Duration;

use Schoolbox\Admin\Settings\FieldType\AbstractRenderer;
use Schoolbox\Config\ConfigMapper;

class Renderer extends AbstractRenderer
{
    /**
     * @inheritdoc
     */
    protected function getTemplate(): string
    {
        return 'duration.twig';
    }


    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_DURATION;
    }
}
