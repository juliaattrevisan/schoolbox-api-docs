import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        () => ({
            template: `
                <div class="l-pad-m" style="height: 500px;">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Getting Started/Contributing/Free Style",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * Styles within `<sbx-free-style>` won't be affected by the Schoolbox CSS
 */
export const OutsideSchoolboxStyles: Story = {
    render: () => ({
        template: `
            <sbx-free-style></sbx-free-style>
        `,
    }),
};

/**
 * Once the you've styled the component, the markup can be added to this story to have the existing Schoolbox styles applied
 */
export const InsideSchoolboxStyles: Story = {
    render: () => ({
        template: `
            <div class="c-select">
                <select id="standard-select">
                    <option value="Option 1">Option 1</option>
                    <option value="Option 2">Option 2</option>
                    <option value="Option 3">Option 3</option>
                    <option value="Option 4">Option 4</option>
                    <option value="Option 5">Option 5</option>
                    <option value="Option length">Option that has too long of a value to fit</option>
                </select>
            </div>
        `,
    }),
};
