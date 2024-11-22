import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * ![draft](/draft.png)
 *
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
        (): { template: string } => ({
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
    <div class="c-fieldset__container">
        <div class="l-flex-row l-flex--align-center">
            <input id="rad2" name="list" type="radio" class="c-input-radio" />
            <label for="rad2" class="c-label c-label--small l-pad-s--horiz">Radio</label>
        </div>
    </div>
</fieldset>
            `,
    }),
    parameters: {
        design: {
            type: "figma",
            url: "https://www.figma.com/design/kcebwjgNDzqyTtXZzgQNMO/UI-Kit?node-id=342-73&t=sZv062qQ9kmTFg6a-4",
        },
    },
};

export const Checked: Story = {
    render: () => ({
        template: `
<fieldset class="c-fieldset">
    <div class="c-fieldset__container">
        <div class="l-flex-row l-flex--align-center">
            <input id="rad2" name="list" type="radio" class="c-input-radio" checked/>
            <label for="rad2" class="c-label c-label--small l-pad-s--horiz">Radio</label>
        </div>
    </div>
</fieldset>
            `,
    }),
    parameters: {
        design: {
            type: "figma",
            url: "https://www.figma.com/design/kcebwjgNDzqyTtXZzgQNMO/UI-Kit?node-id=342-71&t=9ot92RWJV6SUcGzU-4",
        },
    },
};


export const Disabled: Story = {
    render: () => ({
        template: `
<fieldset class="c-fieldset">
    <div class="c-fieldset__container">
        <div class="l-flex-row l-flex--align-center">
            <input id="rad6" name="list" type="radio" class="c-input-radio" disabled />
            <label for="rad6" class="c-label c-label--small l-pad-s--horiz">Radio</label>
        </div>
    </div>
</fieldset>
            `,
    }),
    parameters: {
        design: {
            type: "figma",
            url: "https://www.figma.com/design/kcebwjgNDzqyTtXZzgQNMO/UI-Kit?node-id=342-72&t=9ot92RWJV6SUcGzU-4",
        },
    },
};
export const Error: Story = {
    render: () => ({
        template: `
<fieldset class="c-fieldset">
        <div class="l-flex-row l-flex--align-center">
            <input id="rad6" name="list" type="radio" class="c-input-radio error" />
            <label for="rad6" class="c-label c-label--small l-pad-s--horiz">Radio</label>
        </div>
    </div>
</fieldset>
            `,
    }),
    parameters: {
        design: {
            type: "figma",
            url: "https://www.figma.com/design/kcebwjgNDzqyTtXZzgQNMO/UI-Kit?node-id=342-70&t=9ot92RWJV6SUcGzU-4",
        },
    },
};
