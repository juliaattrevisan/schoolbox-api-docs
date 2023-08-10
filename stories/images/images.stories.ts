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

export const RoundedImage: Story = {
    render: () => ({
        template: `
            <div class="img img--m img--round">
                <div class="img__wrapper">
                    <img src="https://placedog.net/100/200" alt="Placedog 100 by 200 image" />
                </div>
            </div>
        `,
    }),
};

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

export const XXSImage: Story = {
    render: () => ({
        template: `
            <div class="img img--xxs">
                <div class="img__wrapper">
                    <img src="https://placedog.net/100/200" alt="Placedog 100 by 200 image" />
                </div>
            </div>
        `,
    }),
};

export const XSImage: Story = {
    render: () => ({
        template: `
            <div class="img img--xs">
                <div class="img__wrapper">
                    <img src="https://placedog.net/100/200" alt="Placedog 100 by 200 image" />
                </div>
            </div>
        `,
    }),
};

export const XSIcon: Story = {
    render: () => ({
        template: `
            <div class="img img--xs">
                <div class="img__wrapper">
                    <div class="icon icon-calendar"></div>
                </div>
            </div>
        `,
    }),
};

export const SImage: Story = {
    render: () => ({
        template: `
            <div class="img img--s">
                <div class="img__wrapper">
                    <img src="https://placedog.net/100/200" alt="Placedog 100 by 200 image" />
                </div>
            </div>
        `,
    }),
};

export const SIcon: Story = {
    render: () => ({
        template: `
            <div class="img img--s">
                <div class="img__wrapper">
                    <div class="icon icon-calendar"></div>
                </div>
            </div>
        `,
    }),
};

export const MImage: Story = {
    render: () => ({
        template: `
            <div class="img img--m">
                <div class="img__wrapper">
                    <img src="https://placedog.net/100/200" alt="Placedog 100 by 200 image" />
                </div>
            </div>
        `,
    }),
};

export const MIcon: Story = {
    render: () => ({
        template: `
            <div class="img img--m">
                <div class="img__wrapper">
                    <div class="icon icon-calendar"></div>
                </div>
            </div>
        `,
    }),
};

export const LImage: Story = {
    render: () => ({
        template: `
            <div class="img img--l">
                <div class="img__wrapper">
                    <img src="https://placedog.net/100/200" alt="Placedog 100 by 200 image" />
                </div>
            </div>
        `,
    }),
};

export const LIcon: Story = {
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

export const XLImage: Story = {
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

export const XLIcon: Story = {
    render: () => ({
        template: `
            <div class="img img--xl">
                <div class="img__wrapper">
                    <div class="icon icon-calendar"></div>
                </div>
            </div>
        `,
    }),
};

export const XXLImage: Story = {
    render: () => ({
        template: `
            <div class="img img--xxl">
                <div class="img__wrapper">
                    <img src="https://placedog.net/100/200" alt="Placedog 100 by 200 image" />
                </div>
            </div>
        `,
    }),
};

export const XXLIcon: Story = {
    render: () => ({
        template: `
            <div class="img img--xxl">
                <div class="img__wrapper">
                    <div class="icon icon-calendar"></div>
                </div>
            </div>
        `,
    }),
};

export const XXSInitials: Story = {
    render: () => ({
        template: `
            <div class="img img--xxs img--round">
                <div class="img__wrapper" style="background-color: #0066B0;">
                    <div class="img__initials">JL</div>
                </div>
            </div>
        `,
    }),
};

export const XSInitials: Story = {
    render: () => ({
        template: `
            <div class="img img--xs img--round">
                <div class="img__wrapper" style="background-color: #0066B0;">
                    <div class="img__initials">JL</div>
                </div>
            </div>
        `,
    }),
};

export const SInitials: Story = {
    render: () => ({
        template: `
            <div class="img img--s img--round">
                <div class="img__wrapper" style="background-color: #0066B0;">
                    <div class="img__initials">JL</div>
                </div>
            </div>
        `,
    }),
};

export const MInitials: Story = {
    render: () => ({
        template: `
            <div class="img img--m img--round">
                <div class="img__wrapper" style="background-color: #0066B0;">
                    <div class="img__initials">JL</div>
                </div>
            </div>
        `,
    }),
};

export const LInitials: Story = {
    render: () => ({
        template: `
            <div class="img img--l img--round">
                <div class="img__wrapper" style="background-color: #0066B0;">
                    <div class="img__initials">JL</div>
                </div>
            </div>
        `,
    }),
};

export const XLInitials: Story = {
    render: () => ({
        template: `
            <div class="img img--xl img--round">
                <div class="img__wrapper" style="background-color: #0066B0;">
                    <div class="img__initials">JL</div>
                </div>
            </div>
        `,
    }),
};

export const XXLInitials: Story = {
    render: () => ({
        template: `
            <div class="img img--xxl img--round">
                <div class="img__wrapper" style="background-color: #0066B0;">
                    <div class="img__initials">JL</div>
                </div>
            </div>
        `,
    }),
};
