<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Folder\Translator\ModelToArray;

use Schoolbox\Resource\Folder\Model;

/**
 * Folder model to array translator.
 *
 * This translator outputs only the information which is required to display a
 * link to the folder:
 * * ID
 * * name
 * * class codes
 * * view folder link
 */
class LinkOnly
{
    /**
     * @param Model $folder
     * @return array
     */
    public function __invoke(Model $folder): array
    {
        return [
            'id'    => $folder->getId(),
            'name'  => $folder->getName(),
            'title' => $folder->getTitle(),
            'codes' => $folder->getCodes()
                ? $folder->getCodes()->getPropertyValues('code')
                : [],
            '_links'   => [
                'view' => '/homepage/' . $folder->getId(),
            ],
        ];
    }
}
