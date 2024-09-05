import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * ![draft](/draft.png)
 *
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
        (): { template: string } => ({
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

export const Default: Story = {
    render: () => ({
        template: `
            <input id="news_default_expiry-field" class="c-input-text" type="text" required />
            `,
    }),
};

export const Disabled: Story = {
    render: () => ({
        template: `
            <input id="news_default_expiry-field" class="c-input-text" type="text" value="I'm disabled" disabled />
            `,
    }),
};

export const ReadOnly: Story = {
    render: () => ({
        template: `
            <input id="news_default_expiry-field" class="c-input-text" type="text" value="I'm read only" readonly />
            `,
    }),
};

export const HasError: Story = {
    render: () => ({
        template: `
            <input id="news_default_expiry-field" class="c-input-text c-input-text--error" type="text" value="I'm invalid" />
            `,
    }),
};

/**
 * Typically when an action following the button selection fills the input
 * For example a date picker or resource browser
 */
export const WithButton: Story = {
    render: () => ({
        template: `
<div class="c-form-element l-gap--0">
    <input id="news_default_expiry-field" class="c-input-text c-input-text--with-button" type="text" />
    <button class="c-button c-button--with-input l-width-100">
        <sbx-icon name="folder"></sbx-icon>
        <span>Browse Resources</span>
    </button>
</div>
            `,
    }),
};

export const WithLabel: Story = {
    render: () => ({
        template: `
<div class="c-form-element">
    <label for="input1" class="c-label">Default News Archiving Period</label>
    <input id="input1" class="c-input-text" type="text" />
</div>
            `,
    }),
};

export const WithHint: Story = {
    render: () => ({
        template: `
<div class="c-form-element">
    <label for="input2" class="c-label">Default News Archiving Period</label>
    <div class="c-hint">Insert the default length of time from when news is published to when it is archived.</div>
    <input id="input2" class="c-input-text" type="text" />
</div>
            `,
    }),
};

export const WithError: Story = {
    render: () => ({
        template: `
<div class="c-form-element">
    <label for="input3" class="c-label c-label--required">Default News Archiving Period</label>
    <div class="c-hint">Insert the default length of time from when news is published to when it is archived.</div>
    <input id="input3" class="c-input-text c-input-text--error" type="text" required />
    <label id="news-title-error" class="error" for="news-title">This field is required.</label>
</div>
            `,
    }),
};
