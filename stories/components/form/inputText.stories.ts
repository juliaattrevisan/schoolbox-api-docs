import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/ui/forms/_input-text.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/text)
 *
 * `<input>` elements of type text create basic single-line text fields.
 *
 * **Usage notes**:
 * - Must always be accompanied by a `<label>` element.
 * - Avoid using `placeholder` moving forward, consider adding a [Hint](?path=/docs/draft-form-hint--docs) instead.
 *     - https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/placeholder#accessibility_concerns
 *     - https://medium.com/simple-human/10-reasons-why-placeholders-are-problematic-f8079412b960
 * - Avoid using `type="number"` moving forward:
 *     - https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/number#accessibility_concerns
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        () => ({
            template: `
                <div class="c-island">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Draft/Form/Input Text",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.c-input-text`
 */
export const Default: Story = {
    render: () => ({
        template: `
            <input id="news_default_expiry-field" class="c-input-text" type="text" required />
            `,
    }),
};

/**
 * Input disabled
 */
export const Disabled: Story = {
    render: () => ({
        template: `
            <input id="news_default_expiry-field" class="c-input-text" type="text" value="I'm disabled" disabled />
            `,
    }),
};

/**
 * Input read only
 */
export const ReadOnly: Story = {
    render: () => ({
        template: `
            <input id="news_default_expiry-field" class="c-input-text" type="text" value="I'm read only" readonly />
            `,
    }),
};

/**
 * Has a validation error
 */
export const HasError: Story = {
    render: () => ({
        template: `
            <input id="news_default_expiry-field" class="c-input-text c-input-text--error" type="text" value="I'm invalid" />
            `,
    }),
};

/**
 * With a button, typically when an action following the button selection fills the input
 * For example a date picker or resource browser
 */
export const WithButton: Story = {
    render: () => ({
        template: `
            <div class="l-flex-col">
                <input id="news_default_expiry-field" class="c-input-text l-flex__item--1" type="text" />
                <button class="c-button l-width-100 l-mar--0">
                    <sbx-icon name="folder"></sbx-icon>
                    <span>Browse Resources</span>
                </button>
            </div>
            `,
    }),
};
