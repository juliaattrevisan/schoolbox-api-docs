import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * ![draft](/draft.png)
 *
 * **Styles**: `www/static/css/core/ui/forms/_select.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/select)
 *
 * The `<select>` HTML element represents a control that provides a menu of options.
 *
 * **Usage notes**:
 * - Must always be accompanied by a `<label>` element.
 * - The `multiple` attribute is not currently supported as we've not had a use case for it.
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
<div class="c-select">
    <select id="standard-select">
        <option value="Option 1">Option 1</option>
        <option value="Option 2">Option 2</option>
        <option value="Option 3">Option 3</option>
        <option value="Option 4">Option 4</option>
        <option value="Option 5">Option 5</option>
        <option value="Option length">Option that has too long of a value to fit</option>
    </select>
</div>
            `,
    }),
};

export const Disabled: Story = {
    render: () => ({
        template: `
<div class="c-select c-select--disabled">
    <select id="standard-select" disabled>
        <option value="Option 1">Option 1</option>
        <option value="Option 2">Option 2</option>
        <option value="Option 3">Option 3</option>
        <option value="Option 4">Option 4</option>
        <option value="Option 5">Option 5</option>
        <option value="Option length">Option that has too long of a value to fit</option>
    </select>
</div>
            `,
    }),
};

export const HasError: Story = {
    render: () => ({
        template: `
<div class="c-select c-select--error">
    <select id="standard-select">
        <option value="Option 1">Option 1</option>
        <option value="Option 2">Option 2</option>
        <option value="Option 3">Option 3</option>
        <option value="Option 4">Option 4</option>
        <option value="Option 5">Option 5</option>
        <option value="Option length">Option that has too long of a value to fit</option>
    </select>
</div>
            `,
    }),
};
