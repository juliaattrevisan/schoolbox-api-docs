<?php

/**
 * Component_Homepage_EtherpadController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_EtherpadController extends Component_Homepage_ControllerAbstract
{

    /**
     * The sort options made available to the dropdown
     *
     * @var array
     */
    protected $sortFields = array('Name', 'Created', 'Last Updated');

    /**
     * Map human-friendly versions to db-friendly versions
     *
     * @var array
     */
    private $sortMapping = array(
        'Name'         => 'name',
        'Created'      => 'created_at',
        'Last Updated' => 'updated_at',
    );

    /**
     * Get sort key to be used in query, translated from human-friendly
     *
     * @param $key
     * @return string
     */
    private function getSortKey($key)
    {
        if (array_key_exists($key, $this->getSortMapping())) {
            return $this->sortMapping[$key];
        }

        return 'name';
    }

    /**
     * Outputs HTML template defined by config variable 'template'
     *
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'etherpad')) {
            return false;
        }

        $this->parseConfig();

        $this->setBlock($templateHandle, 'source_folder_deleted', 'source_folder_deleted_block');
        $this->setBlock($templateHandle, 'source_folder_exists', 'source_folder_exists_block');        
        $this->setBlock('source_folder_exists', 'etherpad_pad', 'etherpad_pad_block');
        $this->setBlock('source_folder_exists', 'etherpad_has_create', 'etherpad_has_create_block');
        $this->setBlock('etherpad_pad', 'etherpad_has_modify', 'etherpad_has_modify_block');

        $this->setVar(
            array(
                'etherpad_pad_block'        => '',
                'etherpad_has_create_block' => '',
                'etherpad_has_modify_block' => '',
            )
        );

        // Is component source set to different folder, which has been deleted?
        $isDeletedAltSource = $this->isUsingAltSource() && $this->getSource()->isDeleted();

        if($isDeletedAltSource){
            $twigTemplate = 'sub/componentSourceDeleted.twig';
            $twigData = array(
                'componentInstanceId' => $this->getInstance()->getId()
            );
            $this->setVar('SOURCE_FOLDER_DELETED', $this->sitepage->getTwig()->render($twigTemplate, $twigData));
            $this->parse('source_folder_deleted_block','source_folder_deleted');
            $this->setVar('source_folder_exists_block', '');
        } else {
            $this->setVar('source_folder_deleted_block', '');

            /* Find all the pads associated with this list of groups
             * Apply all the component powers
             */
            $padMapper = new \Etherpad\PadMapper();

            $options = array(
                'order' => array(
                    $this->getSortKey($this->getConfig('sort')) => $this->getConfig('sortdir')
                )
            );

            /* Optional limits
             */
            if ($this->getConfig('size')) {
                $options['limit'] = $this->getConfig('size');
            }

            $allPads = $padMapper->findByColumn('homepage_id', $this->getSource()->getId(), $options);

            /* Only provide Modify Document button to those with write access
             */
            foreach ($allPads as $currentPad) {
                $this->setVar(
                    array(
                        'HOMEPAGE_ID' => $this->instance->getIdentifier(),
                        'ETHERPAD_PAD_ID' => $currentPad->getId(),
                        'ETHERPAD_NAME' => $currentPad->getDisplayName(),
                        'ETHERPAD_UPDATED_AT' => $currentPad->getUpdatedAt()->getShort(),
                    )
                );

                if ($this->contentPermission > READ_ACCESS) {
                    $this->parse('etherpad_has_modify_block', 'etherpad_has_modify');
                }

                $this->parse('etherpad_pad_block', 'etherpad_pad', true);

                /* Clear children after parsing out the parent
                 */
                $this->setVar(
                    array(
                        'etherpad_has_modify_block' => ''
                    )
                );
            }

            /* Only provide Create Document button to those with write access
             */
            if ($this->contentPermission > READ_ACCESS) {
                $this->parse('etherpad_has_create_block', 'etherpad_has_create');
            }

            $this->parse('source_folder_exists_block','source_folder_exists');
        }

        return true;
    }

    /* Getters/Setters
     */

    /**
     * Set sort fields
     *
     * @param array $sortFields
     */
    protected function setSortFields($sortFields)
    {
        $this->sortFields = $sortFields;
    }

    /**
     * Set sort mapping
     *
     * @param array $sortMapping
     */
    private function setSortMapping($sortMapping)
    {
        $this->sortMapping = $sortMapping;
    }

    /**
     * Get sort mapping
     *
     * @return array
     */
    private function getSortMapping()
    {
        return $this->sortMapping;
    }
}
