<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Comms\News;

use Alaress\Json\Decorator\IDecorator;

class ItemImageDecorator implements IDecorator
{

    /**
     * Path to an image that should be used to represent the article
     * Is one of three possibilities:
     *      Article's Feature Image (default),
     *      Author's Profile Picture,
     *      School Logo,
     *      None (empty string)
     *
     * @var string|null
     */
    private $itemImage;


    /**
     * MetadataDecorator constructor.
     *
     * @param string|null $itemImage
     *
     */
    public function __construct(?string $itemImage)
    {
        $this->itemImage = $itemImage;
    }

    /**
     * @param array  $json
     * @param object $model
     * @return array
     */
    public function decorate(array $json, $model): array
    {
        if (!$model instanceof Model) {
            // only attempt to decorate if the model is the right type
            return $json;
        }

        /* If a fallback was specified, then decide what to do; you're feeding the dashboard component
         * Otherwise just do default behaviour; you're feeding the News Index screen
         */
        if ('' === (string)$this->itemImage) {
            return $json;
        }

        /* If a feature image for a news article doesn't exist, then use the Fallback specified
         * in the component's config
         */
        if ('' === (string)$json['featureImage']) {
            if ($this->itemImage === 'author') {
                $json['featureImage'] = $json['author']['_links']['avatar']['href'];
            } elseif ($this->itemImage === 'school-logo') {
                $json['featureImage'] = 'find logo from db';
            } else {
                $json['featureImage'] = '';
            }
        }

        return $json;
    }
}
