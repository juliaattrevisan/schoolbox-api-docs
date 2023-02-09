<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Folder\Translator\ModelToArray;

use Schoolbox\Resource\Folder\Model;
use Schoolbox\Resource\Folder\Translator\IModelToArray;

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
class LinkOnly implements IModelToArray
{
    /**
     * Constructor
     *
     * @param int $ancestorCount How many levels of ancestor get translated?
     *                           Any levels of ancestor above this get
     *                           translated with a stub record only.
     */
    public function __construct(private int $ancestorCount)
    {
    }


    /**
     * @param Model $folder
     * @return array
     */
    public function __invoke(Model $folder): array
    {
        return $this->translate($folder, $this->ancestorCount);
    }

    /**
     * Internal method for translating this node, as well as recursively
     * translating its ancestors and descendants.
     *
     * @param Model $folder
     * @param int   $ancestorCount Levels of ancestors to translate.
     * @return array
     */
    private function translate(Model $folder, int $ancestorCount): array
    {
        $parent = null;
        if ($folder->getParent()) {
            if ($ancestorCount > 0) {
                $parent = $this->translate($folder->getParent(), $ancestorCount - 1);
            } else {
                $parent = ['id' => $folder->getParent()->getId()];
            }
        }

        return [
            'id'     => $folder->getId(),
            'name'   => $folder->getName(),
            'title'  => $folder->getTitle(),
            'codes'  => $folder->getCodes()
                ? $folder->getCodes()->getPropertyValues('code')
                : [],
            'parent' => $parent,
            '_links' => [
                'view' => '/homepage/' . $folder->getId(),
            ],
        ];
    }
}
