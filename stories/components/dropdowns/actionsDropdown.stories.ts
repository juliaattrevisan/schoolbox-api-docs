import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
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
type Story = StoryObj<typeof StorybookSlot>;

/**
 *
 */
export const Classes: Story = {
    render: () => ({
        template: `
            <div style="height: 600px;">
                <wc-actions-dropdown content-id="unit-options3" button-label="Actions"></wc-actions-dropdown>
            </div>
            <ul data-dropdown-content="" class="f-dropdown" id="unit-options3">
                <li class="separator">Manage</li>
                <li><a href="/learning/courseMarkbook/5/unit/14/folder/80" class="icon-curriculum">Course Markbook</a></li>
                <li><a href="/course/units/3" data-action="delete-unit" class="icon-delete" title="Delete this unit">Delete</a></li>
            </ul>
        `,
    }),
};
