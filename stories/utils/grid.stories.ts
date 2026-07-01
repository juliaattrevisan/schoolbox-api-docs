import type { Meta, StoryObj } from "storybook-vue3-rsbuild";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/utils/_u-grid.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/CSS/grid)
 *
 * _Further details coming soon_
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        (): { template: string } => ({
            template: `
                <div class="u-pad-xl">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Utility/Grid",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 *
 */
export const Classes: Story = {
    render: () => ({
        template: `

        `,
    }),
};
