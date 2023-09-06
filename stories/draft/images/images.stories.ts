import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    title: "Draft/Images/Images",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Default: Story = {
    render: () => ({
        template: `
            <div class="c-img">
                <div class="c-img__wrapper">
                    <img src="https://placedog.net/100/200" alt="Placedog 100 by 200 image" />
                </div>
            </div>
        `,
    }),
};

/**
 * Image size can be modified with `c-img--xxs`, `c-img--xs`, `c-img--s`, `c-img--m`, `c-img--l`, `c-img--xl`, `c-img--xxl`
 *
 * The default size is `c-img--m`
 */
export const Sizes: Story = {
    render: () => ({
        template: `
            <div class="c-img c-img--xl">
                <div class="c-img__wrapper">
                    <img src="https://placedog.net/100/200" alt="Placedog 100 by 200 image" />
                </div>
            </div>
        `,
    }),
};

/**
 *  Rounded borders are added with `c-img--round`
 */
export const RoundedImage: Story = {
    render: () => ({
        template: `
            <div class="c-img c-img--round">
                <div class="c-img__wrapper">
                    <img src="https://placedog.net/100/200" alt="Placedog 100 by 200 image" />
                </div>
            </div>
        `,
    }),
};

/**
 * The same sizes can be applied to icons
 */
export const Icon: Story = {
    render: () => ({
        template: `
            <div class="c-img c-img--l">
                <div class="c-img__wrapper">
                    <div class="icon icon-calendar"></div>
                </div>
            </div>
        `,
    }),
};

/**
 * _This is a work in progress_
 */
export const Initials: Story = {
    render: () => ({
        template: `
            <div class="c-img">
                <div class="c-img__wrapper" style="background-color: #0066B0;">
                    <div class="img__initials">JL</div>
                </div>
            </div>
        `,
    }),
};
