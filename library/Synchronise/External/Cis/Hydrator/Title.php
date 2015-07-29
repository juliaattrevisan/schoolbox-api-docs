<?php
/**
 * Title sync
 *
 * iSAMS always suffixes title with a dot, we should remove it.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\User\Title;

class Synchronise_External_Cis_Hydrator_Title implements Synchronise_Base_IHydrator
{
    /**
     * Field in externalUser where title is stored
     *
     * @var string
     */
    protected $titleExternalUserField;

    /**
     * List of valid titles
     *
     * @var array
     */
    protected $titleList = [];

    /**
     * @param string $titleExternalUserField
     */
    public function __construct($titleExternalUserField)
    {
        $this->titleExternalUserField = $titleExternalUserField;
        foreach (Title\Mapper::getInstance()->findAll() as $title) {
            /** @var Title\Model $title */
            $this->titleList[$title->getTitle()] = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $title = preg_replace('/\\..*$/', '', $externalUser[$this->titleExternalUserField]);
        if (array_key_exists($title, $this->titleList)) {
            $newUser['title'] = $title;
        } else {
            $newUser['title'] = '';
        }
        return $newUser;
    }
}
