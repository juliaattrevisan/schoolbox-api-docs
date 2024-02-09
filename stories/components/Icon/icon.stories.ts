import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof SbxIcon> = {
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
    title: "Components/Icon",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 *
 */
export const Default: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-icon name="add"></sbx-icon>
        `,
    }),
};

/**
 * Changing the size
 */
export const Size: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-icon name="help" size="xl"></sbx-icon>
        `,
    }),
};

/**
 * Changing the color
 */
export const Color: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-icon name="danger" size="200px" color="red"></sbx-icon>
        `,
    }),
};
