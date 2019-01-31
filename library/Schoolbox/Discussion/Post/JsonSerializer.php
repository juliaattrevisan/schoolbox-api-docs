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
        $author           = $model->getAuthor();
        $serializedAuthor = null;
        if ($author) {
            $serializedAuthor = [
                'id'            => $author->getId(),
                'external_id'   => $author->getExternalId(),
                'enabled'       => $author->getEnabled(),
                'title'         => $author->getTitle(),
                'first_name'    => $author->getFirstname(),
                'last_name'     => $author->getLastname(),
                'full_name'     => $author->getFullName(),
                'profile_image' => $author->getExternalId()
                    ? sprintf('/portrait?userId=%s&size=square80', urlencode($author->getExternalId()))
                    : null,
            ];
        }

        return [
            'id'      => $model->getId(),
            'author'  => $serializedAuthor,
            'content' => $model->getContent(),
            'created' => $model->getCreatedAt()->toRfc3339String(),
            'updated' => $model->getUpdatedAt()->toRfc3339String(),
        ];
    }
}
