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
    title: "Components/Nav/NavActions",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const NavActions: Story = {
    render: () => ({
        template: `
            <div class="c-card">
                <nav class="c-nav-actions l-flex-row">
                    <a class="icon-modify no-text" href="" title="Modify"></a>
                    <a class="icon-modify no-text" href="" title="Modify"></a>
                    <a class="icon-more no-text" href="" title="Actions"></a>
                </nav>
            </div>
        `,
    }),
};
