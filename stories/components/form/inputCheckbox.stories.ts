import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/ui/forms/_input-checkbox.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/checkbox)
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
    title: "Draft/Form/Input Checkbox",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.c-input-checkbox`
 */
export const Default: Story = {
    render: () => ({
        template: `
            <div class="l-flex-row l-flex--align-center">
                <input id="cbx1" type="checkbox" class="c-input-checkbox" />
                <label for="cbx1" class="c-label l-pad-s--horiz">Checkbox</label>
            </div>
            `,
    }),
};

/**
 * Input disabled
 */
export const Disabled: Story = {
    render: () => ({
        template: `
            <div class="l-flex-row l-flex--align-center">
                <input id="cbx2" type="checkbox" class="c-input-checkbox" disabled />
                <label for="cbx2" class="c-label l-pad-s--horiz">Checkbox</label>
            </div>
            `,
    }),
};

/**
 * Intermediate state, to show some children are checked and some are not
 */
export const Intermediate: Story = {
    render: () => ({
        template: `
            <div class="l-flex-row l-flex--align-center">
                <input id="cbx3" type="checkbox" class="c-input-checkbox c-input-checkbox--intermediate" />
                <label for="cbx3" class="c-label l-pad-s--horiz">Checkbox</label>
            </div>
            `,
    }),
};

/**
 * A group of checkboxes, grouped with `<fieldset>` and `<legend>`
 */
export const List: Story = {
    render: () => ({
        template: `
            <fieldset class="c-fieldset">
                <legend class="c-fieldset__legend">List of options</legend>
                <div class="c-hint">Please select all that apply.</div>
                <div class="l-flex-row l-flex--align-center">
                    <input id="cbx4" type="checkbox" class="c-input-checkbox" />
                    <label for="cbx4" class="c-label c-label--set l-pad-s--horiz">Checkbox 1</label>
                </div>
                <div class="l-flex-row l-flex--align-center">
                    <input id="cbx5" type="checkbox" class="c-input-checkbox" />
                    <label for="cbx5" class="c-label c-label--set l-pad-s--horiz">Checkbox 2</label>
                </div>
                <div class="l-flex-row l-flex--align-center">
                    <input id="cbx6" type="checkbox" class="c-input-checkbox" />
                    <label for="cbx6" class="c-label c-label--set l-pad-s--horiz">Checkbox 3</label>
                </div>
            </fieldset>
            `,
    }),
};

/**
 * Checkbox list with children
 *
 * **Note**: For now the functionality of the parent checkbox is not implemented in this story, and it relies on `jquery.select-all.js` to work.
 * Refer to the example at https://vagrant.dev.schoolbox.cloud/pantry/editor for the functionality. This plugin will need to be updated to work with
 * the updated HTML structure.
 */
export const ListWithChildren: Story = {
    render: () => ({
        template: `
            <fieldset class="c-fieldset">
                <legend class="c-fieldset__legend">List of options</legend>
                <div class="c-hint">Please select all that apply.</div>
                <div class="l-flex-row l-flex--align-center">
                    <input id="cbx7" type="checkbox" class="c-input-checkbox" />
                    <label for="cbx7" class="c-label c-label--set l-pad-s--horiz">Checkbox 1</label>
                </div>
                <div class="l-flex-row l-flex--align-center">
                    <input id="cbx8" type="checkbox" class="c-input-checkbox" />
                    <label for="cbx8" class="c-label c-label--set l-pad-s--horiz">Checkbox 2</label>
                </div>
                <div class="l-flex-row l-flex--align-center">
                    <input id="cbx9" type="checkbox" class="c-input-checkbox" />
                    <label for="cbx9" class="c-label c-label--set l-pad-s--horiz">Parent Checkbox 3</label>
                    <sbx-icon name="down-arrow" color="var(--form-border-color)"></sbx-icon>
                </div>
                <div class="l-pad-m--left">
                    <div class="l-flex-col l-gap-s">
                        <div class="l-flex-row l-flex--align-center">
                            <input id="cbx10" type="checkbox" class="c-input-checkbox" />
                            <label for="cbx10" class="c-label c-label--set l-pad-s--horiz">Child Checkbox 1</label>
                        </div>
                        <div class="l-flex-row l-flex--align-center">
                            <input id="cbx11" type="checkbox" class="c-input-checkbox" />
                            <label for="cbx11" class="c-label c-label--set l-pad-s--horiz">Child Checkbox 2</label>
                        </div>
                    </div>
                </div>
            </fieldset>
            `,
    }),
};
