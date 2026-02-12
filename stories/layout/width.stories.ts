import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/layout/_size.scss`
 *
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        (): { template: string } => ({
            template: `
                <div class="l-pad-m">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Layout/Width",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.l-width-100`
 *
 * To stretch an element to the full width of its container
 */
export const Width100: Story = {
    render: () => ({
        template: `
            <div class="l-width-100" style="height: 50px; background: purple;"></div>
        `,
    }),
};

/**
 * `.l-width-50`
 *
 * To stretch an element to half the width of its container
 */
export const Width50: Story = {
    render: () => ({
        template: `
            <div class="l-width-50" style="height: 50px; background: purple;"></div>
        `,
    }),
};
