import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/layout/_container.scss`
 *
 * Currently our main content areas
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        () => ({
            template: `
                <div class="l-pad-m">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Core/Island",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.c-island`
 */
export const Default: Story = {
    render: () => ({
        template: `
            <div class="c-island"></div>
            `,
    }),
};
