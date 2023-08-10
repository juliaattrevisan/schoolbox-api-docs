import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    title: "Images/Images",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Default: Story = {
    render: () => ({
        template: `
            <div class="img">
                <div class="img__wrapper">
                    <img src="https://placedog.net/100/200" alt="Placedog 100 by 200 image" />
                </div>
            </div>
        `,
    }),
};

/**
 * Image size can be modified with `img--xxs`, `img--xs`, `img--s`, `img--m`, `img--l`, `img--xl`, `img--xxl`
 *
 * The default size is `img--m`
 */
export const Sizes: Story = {
    render: () => ({
        template: `
            <div class="img img--xl">
                <div class="img__wrapper">
                    <img src="https://placedog.net/100/200" alt="Placedog 100 by 200 image" />
                </div>
            </div>
        `,
    }),
};

/**
 *  Rounded borders are added with `img--round`
 */
export const RoundedImage: Story = {
    render: () => ({
        template: `
            <div class="img img--round">
                <div class="img__wrapper">
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
            <div class="img img--l">
                <div class="img__wrapper">
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
            <div class="img">
                <div class="img__wrapper" style="background-color: #0066B0;">
                    <div class="img__initials">JL</div>
                </div>
            </div>
        `,
    }),
};
