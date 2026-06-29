import type { Meta, StoryObj } from "storybook-vue3-rsbuild";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/layout/_u-flex.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/CSS/flex)
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        (): { template: string } => ({
            template: `
                <div class="u-pad-m">
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
 * `.u-flex-row`
 */
export const FlexRow: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row u-gap-m">
    <div class="story-demo-box u-flex u-flex--center"><span>1</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>2</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>3</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>4</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>5</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>6</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>7</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>8</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>9</span></div>
</div>
        `,
    }),
};

/**
 * `.u-flex-col`
 */
export const FlexCol: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-col u-gap-m">
    <div class="story-demo-box u-flex u-flex--center"><span>1</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>2</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>3</span></div>
</div>
        `,
    }),
};

/**
 * `.u-flex--wrap-reverse`
 */
export const WrapReverse: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row u-flex--wrap-reverse u-gap-m">
    <div class="story-demo-box u-flex u-flex--center"><span>1</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>2</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>3</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>4</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>5</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>6</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>7</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>8</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>9</span></div>
</div>
        `,
    }),
};

/**
 * `.u-flex--center`
 */
export const Center: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row u-flex--center u-gap-m" style="height: 200px;">
    <div class="story-demo-box u-flex u-flex--center"><span>1</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>2</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>3</span></div>
</div>
        `,
    }),
};

/**
 * `.u-flex--align-center`
 */
export const AlignCenter: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row u-flex--align-center u-gap-m" style="height: 200px;">
    <div class="story-demo-box u-flex u-flex--center"><span>1</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>2</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>3</span></div>
</div>
        `,
    }),
};

/**
 * `.u-flex--justify-center`
 */
export const JustifyCenter: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row u-flex--justify-center u-gap-m">
    <div class="story-demo-box u-flex u-flex--center"><span>1</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>2</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>3</span></div>
</div>
        `,
    }),
};

/**
 * `.u-flex--justify-end`
 */
export const JustifyEnd: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row u-flex--justify-end u-gap-m">
    <div class="story-demo-box u-flex u-flex--center"><span>1</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>2</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>3</span></div>
</div>
        `,
    }),
};

/**
 * `.u-flex--justify-space`
 */
export const JustifySpace: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row u-flex--justify-space u-gap-m">
    <div class="story-demo-box u-flex u-flex--center"><span>1</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>2</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>3</span></div>
</div>
        `,
    }),
};

/**
 * To flex an item to fill the remaining space
 *
 * `.u-flex__item--1`
 */
export const FlexItem: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row u-gap-m">
    <div class="story-demo-box u-flex u-flex--center"><span>1</span></div>
    <div class="story-demo-box u-flex u-flex--center u-flex__item--1"><span>2</span></div>
    <div class="story-demo-box u-flex u-flex--center"><span>3</span></div>
</div>
        `,
    }),
};
