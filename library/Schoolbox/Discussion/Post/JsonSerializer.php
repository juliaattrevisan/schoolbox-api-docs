<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Post;

/**
 * Class JsonSerializer
 */
class JsonSerializer
{
    public function __invoke(Model $model): array
    {
        return [
            'id'      => $model->getId(),
            // Is thread really required here? I've likely requested the post
            // as a part of the thread.
            'author'  => $model->getAuthor(),
            'content' => $model->getContent(),
            'created' => $model->getCreatedAt()->getDatabase(),
            'updated' => $model->getUpdatedAt()->getDatabase(),
        ];
    }
}
