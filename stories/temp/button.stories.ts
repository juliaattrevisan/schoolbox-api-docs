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
                <button class="c-button c-button--submit" type="button">Real Button</button>
                <button type="button" class="c-button">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" aria-hidden="true" focusable="false" class="c-button__icon">
                        <path d="M32 12.408l-11.056-1.607-4.944-10.018-4.944 10.018-11.056 1.607 8 7.798-1.889 11.011 9.889-5.199 9.889 5.199-1.889-11.011 8-7.798z"></path>
                    </svg>
                    <span>Real Button</span>
                </button>
                <a href="javascript:;" class="c-button c-icon-button" aria-label="Icon-only Button">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" aria-hidden="true" focusable="false" class="c-icon-button__icon">
                        <path d="M32 12.408l-11.056-1.607-4.944-10.018-4.944 10.018-11.056 1.607 8 7.798-1.889 11.011 9.889-5.199 9.889 5.199-1.889-11.011 8-7.798z"></path>
                    </svg>
                </a>
            </div>
        `,
    }),
};
