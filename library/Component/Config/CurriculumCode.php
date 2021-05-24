<?php

/**
 * Curriculum code configuration.
 *
 * Select a curriculum code that is attached to this homepage, or free enter a
 * code that isn't attached.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class Component_Config_CurriculumCode extends Component_Config_Abstract
{
    /**
     * {@inheritdoc}
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        // has an internal block for the select element
        $page->setBlock($keyblock, 'curriculumCodes', 'curriculumCodes_block');
        $page->setVar('curriculumCodes_block', '');

        $context = $component->getContext();
        if ($context instanceof Schoolbox\Resource\Folder\Model) {
            foreach ($context->getCurriculumNodes() as $node) {
                // Value is code instead of ID, because we may eventually
                // implement free entry
                $page->setVar([
                    'COMPONENT_CONFIG_CURRICULUMCODE_NAME'     => $node->getCode(),
                    'COMPONENT_CONFIG_CURRICULUMCODE_VALUE'    => $node->getCode(),
                    'COMPONENT_CONFIG_CURRICULUMCODE_SELECTED' => $node->getCode() == $configValue
                        ? ' selected'
                        : '',
                ]);
                $page->parse('curriculumCodes_block', 'curriculumCodes', true);
            }
        }
    }
}
