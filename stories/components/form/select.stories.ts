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
            <select name="pets" id="pet-select" class="c-select">
                <option value="">--Please choose an option--</option>
                <option value="dog">Dog</option>
                <option value="cat">Cat</option>
                <option value="hamster">Hamster</option>
                <option value="parrot">Parrot</option>
                <option value="spider">Spider</option>
                <option value="goldfish">Goldfish</option>
            </select>
            `,
    }),
};

// export const Disabled: Story = {
//     render: () => ({
//         template: `
//             <input id="news_default_expiry-field" class="c-input-text" type="text" value="I'm disabled" disabled />
//             `,
//     }),
// };

// export const ReadOnly: Story = {
//     render: () => ({
//         template: `
//             <input id="news_default_expiry-field" class="c-input-text" type="text" value="I'm read only" readonly />
//             `,
//     }),
// };

// export const HasError: Story = {
//     render: () => ({
//         template: `
//             <input id="news_default_expiry-field" class="c-input-text c-input-text--error" type="text" value="I'm invalid" />
//             `,
//     }),
// };
