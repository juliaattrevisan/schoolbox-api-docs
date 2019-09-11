<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\News;

use Payload\PostTrait;
use Payload\Request;
use Schoolbox\Comms\News;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;

class Update extends Request
{
    use PostTrait;
    use FillFormTrait;

    private $articleId;

    /**
     * @param string     $returnUrl
     * @param News\Model $article
     * @throws InsufficientHydrationException
     */
    public function __construct(string $returnUrl, News\Model $article)
    {
        $this->articleId = $article->getId();

        $this->setReferer("/news/80/update?returnUrl=$returnUrl");

        $this->fillForm($returnUrl, $article);
    }

    protected function getUrl(): string
    {
        return "/news/{$this->articleId}";
    }
}
