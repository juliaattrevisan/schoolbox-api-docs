import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/ui/forms/_input-radio.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/radio)
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
    title: "Draft/Form/Input Radio",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Default: Story = {
    render: () => ({
        template: `
            <fieldset class="c-fieldset">
                <legend class="c-fieldset__legend">List of options</legend>
                <div class="l-flex-row l-flex--align-center">
                    <input id="rad4" name="list" type="radio" class="c-input-radio" checked />
                    <label for="rad4" class="c-label c-label--small l-pad-s--horiz">Radio 1</label>
                </div>
                <div class="l-flex-row l-flex--align-center">
                    <input id="rad5" name="list" type="radio" class="c-input-radio" />
                    <label for="rad5" class="c-label c-label--small l-pad-s--horiz">Radio 2</label>
                </div>
                <div class="l-flex-row l-flex--align-center">
                    <input id="rad6" name="list" type="radio" class="c-input-radio" />
                    <label for="rad6" class="c-label c-label--small l-pad-s--horiz">Radio 3</label>
                </div>
            </fieldset>
            `,
    }),
};

export const Disabled: Story = {
    render: () => ({
        template: `
            <div class="l-flex-row l-flex--align-center">
                <input id="cbx2" type="radio" class="c-input-radio" disabled />
                <label for="cbx2" class="c-label l-pad-s--horiz">Radio</label>
            </div>
            `,
    }),
};

export const WithHint: Story = {
    render: () => ({
        template: `
            <fieldset class="c-fieldset">
                <legend class="c-fieldset__legend">List of options</legend>
                <div class="c-hint">Please select the correct option.</div>
                <div class="l-flex-row l-flex--align-center">
                    <input id="rad4" name="list" type="radio" class="c-input-radio" checked />
                    <label for="rad4" class="c-label c-label--small l-pad-s--horiz">Radio 1</label>
                </div>
                <div class="l-flex-row l-flex--align-center">
                    <input id="rad5" name="list" type="radio" class="c-input-radio" />
                    <label for="rad5" class="c-label c-label--small l-pad-s--horiz">Radio 2</label>
                </div>
                <div class="l-flex-row l-flex--align-center">
                    <input id="rad6" name="list" type="radio" class="c-input-radio" />
                    <label for="rad6" class="c-label c-label--small l-pad-s--horiz">Radio 3</label>
                </div>
            </fieldset>
            `,
    }),
};
