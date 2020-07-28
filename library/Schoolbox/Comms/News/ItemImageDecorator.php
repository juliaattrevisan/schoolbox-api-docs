<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Comms\News;

use Alaress\Json\Decorator\IDecorator;
use Schoolbox\Resource\Storage\IStorage;

class ItemImageDecorator implements IDecorator
{


    /**
     * An IStorage instance representing the school logo
     *
     * @var IStorage|null
     */
    private $logo;


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
     * @param IStorage|null $logo
     * @param string|null   $itemImage
     */
    public function __construct(?IStorage $logo, ?string $itemImage)
    {
        $this->logo = $logo;
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
         *
         * None and Author result in feature image remaining empty
         */
        if ('' === (string)$json['featureImage']) {
            if ($this->itemImage === \Component_Config_ItemImage::IMAGE_SCHOOL_LOGO && null !== $this->logo) {
                $json['featureImage'] = [
                    'hash'   => $this->logo->getHash(),
                    'size'   => $this->logo->getSize(),
                    'mime'   => $this->logo->getMimetype()->getMime(),
                    '_links' => [
                        'fetch' => [
                            'href' => "/storage/fetch.php?hash={$this->logo->getHash()}",
                            'auth' => ''
                        ],
                        'image' => [
                            'href' => "/storage/image.php?hash={$this->logo->getHash()}",
                            'auth' => '',
                        ]
                    ]
                ];
            }
        }

        return $json;
    }
}
