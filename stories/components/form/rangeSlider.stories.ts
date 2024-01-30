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
    title: "Components/Form/Range Slider",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Standard: Story = {
    render: () => ({
        template: `
            <div>
                <label for="column-width">Column Width</label>
                <input type="range" id="column-width" name="column-width" min="3" max="9" step="1" value="7" />
            </div>
        `,
    }),
};
