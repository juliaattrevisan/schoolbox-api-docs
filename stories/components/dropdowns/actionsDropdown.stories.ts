import type { Meta, StoryObj } from "@storybook/vue3";
import WcActionsDropdown from "@schoolboxVue/components/dropDown/wc-actions-dropdown.ce.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof WcActionsDropdown> = {
    component: WcActionsDropdown,
    decorators: [
        () => ({
            template: `
                <div class="l-pad-xl">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Components/Dropdowns/Actions Dropdown",
};
export default meta;
type Story = StoryObj<typeof WcActionsDropdown>;

/**
 *
 */
export const Default: Story = {
    render: () => ({
        components: { WcActionsDropdown },
        template: `
            <div style="height: 600px;">
                <wc-actions-dropdown content-id="unit-options3"></wc-actions-dropdown>
                <ul data-dropdown-content="" class="f-dropdown" id="unit-options3">
                    <li class="separator">Manage</li>
                    <li><a href="/learning/courseMarkbook/5/unit/14/folder/80" class="icon-curriculum">Course Markbook</a></li>
                    <li><a href="/course/units/3" data-action="delete-unit" class="icon-delete" title="Delete this unit">Delete</a></li>
                </ul>
            </div>
        `,
    }),
};
