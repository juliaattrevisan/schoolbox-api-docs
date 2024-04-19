import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/ui/forms/_select.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/select)
 *
 * The <`select>` HTML element represents a control that provides a menu of options.
 *
 * **Usage notes**:
 * - Must always be accompanied by a `<label>` element.
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
    title: "Draft/Form/Select",
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
