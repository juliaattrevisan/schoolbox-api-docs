import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * ![draft](/draft.png)
 *
 * **Styles**: `www/static/css/core/ui/forms/_fieldset.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/fieldset)
 *
 * **Usage notes**:
 * - Fieldset is used for grouping sets of input components. It is necessary to use a fieldset with radio and checkbox components..
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
    title: "Draft/Form/Fieldset",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;


export const ListOfCheckboxes: Story = {
    render: () => ({
        template: `
<fieldset class="c-fieldset">
    <div class="c-fieldset__container">
        <legend class="c-label">List of options</legend>
        <div class="l-flex-row l-flex--align-center">
            <input id="cbx4" type="checkbox" class="c-input-checkbox" />
            <label for="cbx4" class="c-label c-label--small l-pad-s--horiz">Checkbox 1</label>
        </div>
        <div class="l-flex-row l-flex--align-center">
            <input id="cbx5" type="checkbox" class="c-input-checkbox" />
            <label for="cbx5" class="c-label c-label--small l-pad-s--horiz">Checkbox 2</label>
        </div>
        <div class="l-flex-row l-flex--align-center">
            <input id="cbx6" type="checkbox" class="c-input-checkbox" />
            <label for="cbx6" class="c-label c-label--small l-pad-s--horiz">Checkbox 3</label>
        </div>
    </div>
</fieldset>
            `,
    }),
};

export const ListOfCheckboxesWithHint: Story = {
    render: () => ({
        template: `
<fieldset class="c-fieldset">
    <div class="c-fieldset__container">
        <legend class="c-label">List of options</legend>
        <div class="c-hint">Please select all that apply.</div>
        <div class="l-flex-row l-flex--align-center">
            <input id="cbx4a" type="checkbox" class="c-input-checkbox" />
            <label for="cbx4a" class="c-label c-label--small l-pad-s--horiz">Checkbox 1</label>
        </div>
        <div class="l-flex-row l-flex--align-center">
            <input id="cbx5a" type="checkbox" class="c-input-checkbox" />
            <label for="cbx5a" class="c-label c-label--small l-pad-s--horiz">Checkbox 2</label>
        </div>
        <div class="l-flex-row l-flex--align-center">
            <input id="cbx6a" type="checkbox" class="c-input-checkbox" />
            <label for="cbx6a" class="c-label c-label--small l-pad-s--horiz">Checkbox 3</label>
        </div>
    </div>
</fieldset>
            `,
    }),
};

export const InlineListOfCheckboxes: Story = {
    render: () => ({
        template: `
<fieldset class="c-fieldset">
    <div class="c-fieldset__container">
        <legend class="c-label">List of options</legend>
        <div class="c-hint">Please select all that apply.</div>
        <div class="l-flex-row l-gap-m">
            <div class="l-flex-row l-flex--align-center">
                <input id="cbx4a" type="checkbox" class="c-input-checkbox" />
                <label for="cbx4a" class="c-label c-label--small l-pad-s--horiz">Checkbox 1</label>
            </div>
            <div class="l-flex-row l-flex--align-center">
                <input id="cbx5a" type="checkbox" class="c-input-checkbox" />
                <label for="cbx5a" class="c-label c-label--small l-pad-s--horiz">Checkbox 2</label>
            </div>
            <div class="l-flex-row l-flex--align-center">
                <input id="cbx6a" type="checkbox" class="c-input-checkbox" />
                <label for="cbx6a" class="c-label c-label--small l-pad-s--horiz">Checkbox 3</label>
            </div>
        </div>
    </div>
</fieldset>
            `,
    }),
};

/**
 * **Note**: For now the functionality of the parent checkbox is not implemented in this story, and it relies on `jquery.select-all.js` to work.
 * Refer to the example at https://vagrant.dev.schoolbox.cloud/pantry/editor for the functionality. This plugin will need to be updated to work with
 * the updated HTML structure.
 */
export const ListOfCheckboxesWithChildren: Story = {
    render: () => ({
        template: `
<fieldset class="c-fieldset">
    <div class="c-fieldset__container">
        <legend class="c-label">List of options</legend>
        <div class="c-hint">Please select all that apply.</div>
        <div class="l-flex-row l-flex--align-center">
            <input id="cbx7" type="checkbox" class="c-input-checkbox" />
            <label for="cbx7" class="c-label c-label--small l-pad-s--horiz">Checkbox 1</label>
        </div>
        <div class="l-flex-row l-flex--align-center">
            <input id="cbx8" type="checkbox" class="c-input-checkbox" />
            <label for="cbx8" class="c-label c-label--small l-pad-s--horiz">Checkbox 2</label>
        </div>
        <div class="l-flex-row l-flex--align-center">
            <input id="cbx9" type="checkbox" class="c-input-checkbox" />
            <label for="cbx9" class="c-label c-label--small l-pad-s--horiz">Parent Checkbox 3</label>
            <sbx-icon name="down-arrow" color="var(--form-border-color)"></sbx-icon>
        </div>
        <div class="l-pad-s--left">
            <div class="l-flex-col l-gap-s">
                <div class="l-flex-row l-flex--align-center">
                    <input id="cbx10" type="checkbox" class="c-input-checkbox" />
                    <label for="cbx10" class="c-label c-label--small l-pad-s--horiz">Child Checkbox 1</label>
                </div>
                <div class="l-flex-row l-flex--align-center">
                    <input id="cbx11" type="checkbox" class="c-input-checkbox" />
                    <label for="cbx11" class="c-label c-label--small l-pad-s--horiz">Child Checkbox 2</label>
                </div>
            </div>
        </div>
    </div>
</fieldset>
            `,
    }),
};

export const ListOfRadio: Story = {
    render: () => ({
        template: `
<fieldset class="c-fieldset">
    <div class="c-fieldset__container">
        <legend class="c-label">List of options</legend>
        <div class="l-flex-row l-flex--align-center">
            <input id="rad1" name="list" type="radio" class="c-input-radio" />
            <label for="rad1" class="c-label c-label--small l-pad-s--horiz">Radio 1</label>
        </div>
        <div class="l-flex-row l-flex--align-center">
            <input id="rad2" name="list" type="radio" class="c-input-radio" checked />
            <label for="rad2" class="c-label c-label--small l-pad-s--horiz">Radio 2</label>
        </div>
        <div class="l-flex-row l-flex--align-center">
            <input id="rad3" name="list" type="radio" class="c-input-radio" disabled/>
            <label for="rad3" class="c-label c-label--small l-pad-s--horiz">Radio 3</label>
        </div>
        <div class="l-flex-row l-flex--align-center">
            <input id="rad4" name="list" type="radio" class="c-input-radio error" />
            <label for="rad4" class="c-label c-label--small l-pad-s--horiz">Radio 4</label>
        </div>
    </div>
</fieldset>
            `,
    }),
    parameters: {
        design: {
            type: "figma",
            url: "https://www.figma.com/design/kcebwjgNDzqyTtXZzgQNMO/UI-Kit?node-id=342-74&t=9ot92RWJV6SUcGzU-4",
        },
    },
};

export const ListOfRadioWithHint: Story = {
    render: () => ({
        template: `
<fieldset class="c-fieldset">
    <div class="c-fieldset__container">
        <legend class="c-label">List of options</legend>
        <div class="c-hint">Please select the correct option.</div>
        <div class="l-flex-row l-flex--align-center">
            <input id="rad7" name="list" type="radio" class="c-input-radio" checked />
            <label for="rad7" class="c-label c-label--small l-pad-s--horiz">Radio 1</label>
        </div>
        <div class="l-flex-row l-flex--align-center">
            <input id="rad8" name="list" type="radio" class="c-input-radio" />
            <label for="rad8" class="c-label c-label--small l-pad-s--horiz">Radio 2</label>
        </div>
        <div class="l-flex-row l-flex--align-center">
            <input id="rad9" name="list" type="radio" class="c-input-radio" />
            <label for="rad9" class="c-label c-label--small l-pad-s--horiz">Radio 3</label>
        </div>
    </div>
</fieldset>
            `,
    }),
};

export const InlineListOfRadio: Story = {
    render: () => ({
        template: `
<fieldset class="c-fieldset">
    <div class="c-fieldset__container">
        <legend class="c-label">List of options</legend>
        <div class="c-hint">Please select the scorrect option.</div>
        <div class="l-flex-row l-gap-m">
            <div class="l-flex-row l-flex--align-center">
                <input id="rad7a" name="list" type="radio" class="c-input-radio" checked />
                <label for="rad7a" class="c-label c-label--small l-pad-s--horiz">Radio 1</label>
            </div>
            <div class="l-flex-row l-flex--align-center">
                <input id="rad8a" name="list" type="radio" class="c-input-radio" />
                <label for="rad8a" class="c-label c-label--small l-pad-s--horiz">Radio 2</label>
            </div>
            <div class="l-flex-row l-flex--align-center">
                <input id="rad9a" name="list" type="radio" class="c-input-radio" />
                <label for="rad9a" class="c-label c-label--small l-pad-s--horiz">Radio 3</label>
            </div>
        </div>
    </div>
</fieldset>
            `,
    }),
};
