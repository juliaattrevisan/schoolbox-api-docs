<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\News;

use Schoolbox\Comms\News;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;

trait FillFormTrait
{
    /**
     * Set a requests form param
     * @see \Payload\Request::setFormParam()
     *
     * @param string $name
     * @param        $value
     */
    abstract public function setFormParam(string $name, $value): void;

    /**
     * @param string     $returnUrl
     * @param News\Model $article
     * @throws InsufficientHydrationException
     */
    private function fillForm(string $returnUrl, News\Model $article): void
    {
        $this->setFormParam('returnUrl', $returnUrl);
        $this->setFormParam('title', $article->getTitle());
        $this->setFormParam('body', $article->getBody());
        $this->setFormParam('blurb', $article->getBlurb());

        $codex     = new News\Audience\Segment\Codec();
        $fragments = [];
        $segments  = $article->getAudience();
        if ($segments !== null) {
            foreach ($segments as $segment) {
                $fragments[] = $codex->encode($segment);
            }
        }
        $this->setFormParam('audience', implode(',', $fragments));

        $publishDate = $article->getPublishAt();
        $this->setFormParam('publishAt', $publishDate ? $publishDate->getUsingFormat(\Alaress_Date_Format::FORMAT_DATE_PICKER) : '');
        $expiresAt = $article->getExpiresAt();
        $this->setFormParam('expiresAt', $expiresAt ? $expiresAt->getUsingFormat(\Alaress_Date_Format::FORMAT_DATE_PICKER) : '');
        $this->setFormParam('draft', 'true');
    }
}
