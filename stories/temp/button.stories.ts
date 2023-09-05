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
                    <svg version="1.2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false" class="c-icon"><path  d="m6 0h4v4h-4z"/><path  d="m6 6h4v4h-4z"/><path  d="m6 12h4v4h-4z"/></svg>
                    <span>Real Button</span>
                </button>
                <button type="button" class="c-button c-button--icon-only" aria-label="Icon-only Button">
                    <svg version="1.2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false" class="c-icon"><path  d="m6 0h4v4h-4z"/><path  d="m6 6h4v4h-4z"/><path  d="m6 12h4v4h-4z"/></svg>
                </button>
                <button type="button" class="c-button c-button--submit">
                    <svg version="1.2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false" class="c-icon"><path  d="m6 0h4v4h-4z"/><path  d="m6 6h4v4h-4z"/><path  d="m6 12h4v4h-4z"/></svg>
                    <span>Real Button</span>
                </button>
                <button type="button" class="c-button c-button--icon-only c-button--no-bkg" aria-label="Icon-only Button">
                    <svg version="1.2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false" class="c-icon"><path  d="m6 0h4v4h-4z"/><path  d="m6 6h4v4h-4z"/><path  d="m6 12h4v4h-4z"/></svg>
                </button>
            </div>
        `,
    }),
};
