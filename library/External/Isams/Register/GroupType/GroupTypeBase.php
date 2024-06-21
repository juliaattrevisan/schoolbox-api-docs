<?php

declare(strict_types=1);

namespace External\Isams\Register\GroupType;

use External\Isams\Register\CacheHelper;
use Schoolbox\User;

abstract class GroupTypeBase implements GroupTypeInterface
{
    private const TYPE_TEXTBOX_FIELD = 'TextboxField';
    private const TYPE_URL = 'url';
    private const TYPE_HTML = 'html';

    protected string $collectionName;

    /**
     * @param CacheHelper $cacheHelper
     * @param User $user
     * @param array $register
     * @param array $config
     * @param array $metaFields
     */
    public function __construct(
        protected CacheHelper $cacheHelper,
        protected User $user,
        protected array $register,
        protected array $config,
        protected array $metaFields,
    ) {
        $this->collectionName = $this->config['recordCollection'];
    }

    abstract protected function retrieveRecords(): mixed;
    abstract public function process(array $out): array;

    /**
     * Prepare the user data with their appropriate type and value
     *
     * @param array         $field
     * @param null|string   $value
     * @return array
     */
    protected function prepareUserFieldData(array $field, ?string $value): array
    {

        $type = $field['type'];
        //Only process if there's a value
        if (null !== $value) {
            // if TextboxField confirm the field type  (url / html?)
            if (self::TYPE_TEXTBOX_FIELD === $type) {
                // is this an url, if so get twig to format as href
                $url = new \Url($value);
                if ($url->isUrl()) {
                    $type = self::TYPE_URL;
                }

                // is html, then get twig to sanitise with `| purify`
                if (0 !== preg_match("/<[^<]+>/", $value, $m)) {
                    $type = self::TYPE_HTML;
                }
            }
        }

        return [
            $field['name'],
            $type,
            $value
        ];
    }
}
