<?php
/**
 * Author: andy@modernwebservices.com.au
 * Created: 28/09/18 12:09 PM
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\Renderer;

use Schoolbox\Config\ConfigMapper;

class Boolean extends Checkbox
{

    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_BOOLEAN;
    }
}
