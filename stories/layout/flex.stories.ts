import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/layout/_l-flex.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/CSS/flex)
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        (): { template: string } => ({
            template: `
                <div class="l-pad-m">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Layout/Flex",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.l-flex-row`
 */
export const FlexRow: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row l-gap-m">
    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>2</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>4</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>5</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>6</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>7</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>8</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>9</span></div>
</div>
        `,
    }),
};

/**
 * `.l-flex-col`
 */
export const FlexCol: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-col l-gap-m">
    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>2</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
</div>
        `,
    }),
};

/**
 * `.l-flex--wrap-reverse`
 */
export const WrapReverse: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row l-flex--wrap-reverse l-gap-m">
    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>2</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>4</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>5</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>6</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>7</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>8</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>9</span></div>
</div>
        `,
    }),
};

/**
 * `.l-flex--center`
 */
export const Center: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row l-flex--center l-gap-m" style="height: 200px;">
    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>2</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
</div>
        `,
    }),
};

/**
 * `.l-flex--align-center`
 */
export const AlignCenter: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row l-flex--align-center l-gap-m" style="height: 200px;">
    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>2</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
</div>
        `,
    }),
};

/**
 * `.l-flex--justify-center`
 */
export const JustifyCenter: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row l-flex--justify-center l-gap-m">
    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>2</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
</div>
        `,
    }),
};

/**
 * `.l-flex--justify-end`
 */
export const JustifyEnd: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row l-flex--justify-end l-gap-m">
    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>2</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
</div>
        `,
    }),
};

/**
 * `.l-flex--justify-space`
 */
export const JustifySpace: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row l-flex--justify-space l-gap-m">
    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>2</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
</div>
        `,
    }),
};

/**
 * To flex an item to fill the remaining space
 *
 * `.l-flex__item--1`
 */
export const FlexItem: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row l-gap-m">
    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
    <div class="story-demo-box l-flex l-flex--center l-flex__item--1"><span>2</span></div>
    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
</div>
        `,
    }),
};
