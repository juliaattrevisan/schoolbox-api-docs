import type { Meta, StoryObj } from "storybook-vue3-rsbuild";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * ![draft](/draft.png)
 *
 * **Styles**: `www/static/css/core/ui/_search-select.scss`
 *
 * Indicates an active item, currently used in the `<search-select` component to show selected items.
 * Moving forward will be added into filters and news audience bricks
 *
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
    title: "Draft/Form/Tag",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Default: Story = {
    render: () => ({
        template: `
<div class="l-flex-row l-gap-s">
    <div class="sbx-tag">
        <span class="sbx-tag__name-span">Community</span>
    </div>
</div>        `,
    }),
};

export const Multiple: Story = {
    render: () => ({
        template: `
<div class="l-flex-row l-gap-s">
    <div class="sbx-tag">
        <span class="sbx-tag__name-span">Community</span>
    </div>
    <div class="sbx-tag">
        <span class="sbx-tag__name-span">Education</span>
    </div>
</div>        `,
    }),
};

export const Stretch: Story = {
    render: () => ({
        template: `
<div class="l-flex-row l-gap-s">
    <div class="sbx-tag l-flex__item--1">
        <span class="sbx-tag__name-span">Community</span>
    </div>
</div>        `,
    }),
};

export const WithButton: Story = {
    render: () => ({
        template: `
<div class="l-flex-row l-gap-s">
    <div class="sbx-tag">
        <button type="button" class="sbx-tag__name-button">Community</button>
    </div>
</div>        `,
    }),
};

export const WithClose: Story = {
    render: () => ({
        template: `
<div class="l-flex-row l-gap-s">
    <div class="sbx-tag">
        <button type="button" class="sbx-tag__name-button">Community</button>
        <button type="button" class="sbx-tag__remove-button" title="Remove Community" aria-label="Remove Community">
            <sbx-icon name="close"></sbx-icon>
        </button>
    </div>
</div>        `,
    }),
};
