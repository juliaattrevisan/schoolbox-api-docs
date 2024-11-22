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

export const List: Story = {
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
            url: "https://www.figma.com/design/kcebwjgNDzqyTtXZzgQNMO/UI-Kit?node-id=342-71&t=9ot92RWJV6SUcGzU-4",
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

export const WithHint: Story = {
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

export const Inline: Story = {
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
