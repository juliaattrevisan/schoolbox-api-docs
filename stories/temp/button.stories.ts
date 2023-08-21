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
                <div class="l-pad-xl">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Temp/Button",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Standard: Story = {
    render: () => ({
        template: `
            <div class="l-flex-row l-gap-m">
                <a href="javascript:;" class="c-button">Button Link</a>
                <button class="c-button" type="button">Real Button</button>
            </div>
        `,
    }),
};
