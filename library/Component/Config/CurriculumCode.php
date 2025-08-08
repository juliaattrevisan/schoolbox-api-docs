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
    public function parse(SitePage $page, $key, $keyblock, $configValue, ?Component_ControllerAbstract $component = null)
    {
        // has an internal block for the select element
        $page->setBlock($keyblock, 'curriculumCodes', 'curriculumCodes_block');
        $page->setVar('curriculumCodes_block', '');
        $page->setBlock($keyblock, 'curriculumCodesDisabled', 'curriculumCodesDisabled_block');
        $page->setVar('curriculumCodesDisabled_block', '');

        $context = $component->getContext();
        $nodeSelected = false;
        if ($context instanceof Schoolbox\Resource\Folder\Model) {
            if (empty($configValue)) {
                $nodeSelected = true;
            }
            $page->setVar(
                [
                    'COMPONENT_CONFIG_CURRICULUMCODE_NAME'     => 'All',
                    'COMPONENT_CONFIG_CURRICULUMCODE_VALUE'    => '',
                    'COMPONENT_CONFIG_CURRICULUMCODE_SELECTED' => empty($configValue)
                        ? ' selected'
                        : '',
                ]
            );
            $page->parse('curriculumCodes_block', 'curriculumCodes', true);
            foreach ($context->getViewableCurriculumNodes() as $node) {
                if (!$node->getEnabled()) {
                    continue;
                }
                if ($node->getCode() === (string)$configValue) {
                    $nodeSelected = true;
                }
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
            // if config value did not match any of the above, show empty line
            if (!$nodeSelected) {
                $page->parse('curriculumCodesDisabled_block', 'curriculumCodesDisabled', true);
            }
        }
    }
}
