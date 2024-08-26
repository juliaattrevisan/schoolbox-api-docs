import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/ui/_images.scss`
 *
 * A class to wrap any styles for images. Currently, it includes...
 *
 * -   Seven default sizes for square images controlled by custom properties
 * -   Support for icons within the same image container classes
 * -   The ability to round those images with a modifier class
 * -   Styles to enable initials where a user doesn't have a profile image
 * -   Automated positioning of images within the front end so the contents are
 *     centred even if the image isn't square
 *
 * Generally there's three layers to how you implement these images.
 *
 * -   A container (the topmost level) that just handles positioning of the object within its parent
 *     and acts as a barrier so the wrapper is shielded from being rescaled by rules affecting the parent
 * -   A wrapper (the middle layer) that solely handles hiding the overflow of the image, positioning it
 *     centrally and applying any rounding. The wrapper handles this so that if the container layer above
 *     it is scaled in any way, the image rounding won't be modified or stretched.
 * -   The image itself (the lowest layer), which is literally just the \<img\>, an icon, or some initials
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    title: "Components/Images/Images",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Default: Story = {
    render: () => ({
        template: `
            <div class="c-img">
                <div class="c-img__wrapper">
                    <img src="/user.jpg" alt="Placedog 100 by 200 image" />
                </div>
            </div>
        `,
    }),
};

/**
 * Image size can be modified with `.c-img--xxs`, `.c-img--xs`, `.c-img--s`, `.c-img--m`, `.c-img--l`, `.c-img--xl`, `.c-img--xxl`
 *
 * The default size is `.c-img--m`
 */
export const Sizes: Story = {
    render: () => ({
        template: `
            <div class="c-img c-img--xl">
                <div class="c-img__wrapper">
                    <img src="/user.jpg" alt="Placedog 100 by 200 image" />
                </div>
            </div>
        `,
    }),
};

/**
 *  Rounded borders are added with `.c-img--round`
 */
export const RoundedImage: Story = {
    render: () => ({
        template: `
            <div class="c-img c-img--round">
                <div class="c-img__wrapper">
                    <img src="/user.jpg" alt="Placedog 100 by 200 image" />
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
 * ![draft](/draft.png)
 *
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
