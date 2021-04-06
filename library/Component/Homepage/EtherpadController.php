<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_EtherpadController extends Component_Homepage_ControllerAbstract
{

    /**
     * The sort options made available to the dropdown
     *
     * @var array
     */
    protected $sortFields = ['Name', 'Created', 'Last Updated'];

    /**
     * Map human-friendly versions to db-friendly versions
     *
     * @var array
     */
    private $sortMapping = [
        'Name'         => 'name',
        'Created'      => 'created_at',
        'Last Updated' => 'updated_at',
    ];

    /**
     * Get sort key to be used in query, translated from human-friendly
     *
     * @param string $key
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
        if (!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailableTwig($templateHandle);
        }

        $this->parseConfig();

        $options = [
            'order' => [
                $this->getSortKey($this->getConfig('sort')) => $this->getConfig('sortdir'),
            ],
        ];
        /* Find all the pads associated with this list of groups
        * Apply all the component powers
        */

        $padMapper = new \Etherpad\PadMapper();
        $allPads   = $padMapper->findByColumn('homepage_id', $this->getSource()->getId(), $options);

        $this->setBlock($templateHandle, 'has_docs', 'has_docs_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        if (count($allPads) === 0) {
            $this->setVar(
                [
                    'has_docs_block'    => '',
                    'empty_write_block' => '',
                    'SOURCE_ID'         => $this->getSource()->getId(),
                ]
            );

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                return false;
            }

            $this->parse('empty_state_block', 'empty_state');
        } else {
            $this->setBlock('has_docs', 'source_folder_deleted', 'source_folder_deleted_block');
            $this->setBlock('has_docs', 'source_folder_exists', 'source_folder_exists_block');
            $this->setBlock('source_folder_exists', 'etherpad_pad', 'etherpad_pad_block');
            $this->setBlock('source_folder_exists', 'etherpad_has_create', 'etherpad_has_create_block');
            $this->setBlock('etherpad_pad', 'etherpad_has_modify', 'etherpad_has_modify_block');

            $this->setVar(
                [
                    'empty_state_block'           => '',
                    'etherpad_pad_block'          => '',
                    'etherpad_has_create_block'   => '',
                    'etherpad_has_modify_block'   => '',
                    'source_folder_deleted_block' => '',
                ]
            );

            if (!$this->isSourceRenderable()) {
                return $this->renderSourceUnavailable();
            } else {
                /* Optional limits
                 */
                if ($this->getConfig('size')) {
                    $options['limit'] = $this->getConfig('size');
                }

                /* Only provide Modify Document button to those with write access
                 */
                foreach ($allPads as $currentPad) {
                    $this->setVar(
                        [
                            'HOMEPAGE_ID'         => $this->getInstance()->getIdentifier(),
                            'ETHERPAD_PAD_ID'     => $currentPad->getId(),
                            'ETHERPAD_NAME'       => Scrub::text($currentPad->getDisplayName()),
                            'ETHERPAD_UPDATED_AT' => $currentPad->getUpdatedAt()->getShort(),
                        ]
                    );

                    if ($this->getContentPermission() > UserAccess::ACCESS_READ) {
                        $this->parse('etherpad_has_modify_block', 'etherpad_has_modify');
                    }

                    $this->parse('etherpad_pad_block', 'etherpad_pad', true);

                    /* Clear children after parsing out the parent
                     */
                    $this->setVar('etherpad_has_modify_block', '');
                }

                /* Only provide Create Document button to those with write access
                 */
                if ($this->getContentPermission() > UserAccess::ACCESS_READ) {
                    $this->setVar('SOURCE_ID', $this->getSource()->getId());
                    $this->parse('etherpad_has_create_block', 'etherpad_has_create');
                }

                $this->parse('source_folder_exists_block', 'source_folder_exists');
            }

            $this->parse('has_docs_block', 'has_docs');
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
