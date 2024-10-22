<?php

namespace Schoolbox\Resource\Folder\Translator\ModelToArray;

use Schoolbox\Resource\Folder\Model;
use Schoolbox\Resource\Folder\Translator\IModelToArray;
use Schoolbox\Curriculum\Api\Translator\ModelToArray as CurriculumTranslator;

class ModelToArray implements IModelToArray
{
    public function __construct(
        private CurriculumTranslator $curriculumTranslator,
        private int $ancestorCount,
    ) {
    }

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
            'curriculumNodes' => $folder->getViewableCurriculumNodes()
                                   ?->map(fn($curr) => call_user_func($this->curriculumTranslator, $curr)),
            'parent' => $parent,
            '_links' => [
                'view' => '/homepage/' . $folder->getId(),
            ],
        ];
    }
}
