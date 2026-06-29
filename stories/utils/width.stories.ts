import type { Meta, StoryObj } from "storybook-vue3-rsbuild";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/utils/_u-size.scss`
 *
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        (): { template: string } => ({
            template: `
                <div class="u-pad-m">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Utility/Width",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.u-width-100`
 *
 * To stretch an element to the full width of its container
 */
export const Width100: Story = {
    render: () => ({
        template: `
            <div class="u-width-100" style="height: 50px; background: purple;"></div>
        `,
    }),
};

/**
 * `.u-width-50`
 *
 * To stretch an element to half the width of its container
 */
export const Width50: Story = {
    render: () => ({
        template: `
            <div class="u-width-50" style="height: 50px; background: purple;"></div>
        `,
    }),
};
