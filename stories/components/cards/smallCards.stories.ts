import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    title: "Components/Cards/Small",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * We just add the `c-card--small` modifier class to the card
 */
export const Default: Story = {
    render: () => ({
        template: `
            <div class="c-card c-card--small">
                <div class="c-card__main">
                    <div class="c-img c-img--s">
                        <div class="c-img__wrapper">
                            <div class="icon icon-calendar"></div>
                        </div>
                    </div>
                    <div class="c-card__main-content">
                        <span>Jane submitted on time</span>
                        <span class="meta">3:47PM Tuesday 13th July 2022</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const WithLink: Story = {
    render: () => ({
        template: `
            <div class="c-card c-card--small">
                <div class="c-card__main">
                    <div class="c-img c-img--s">
                        <div class="c-img__wrapper">
                            <div class="icon icon-calendar"></div>
                        </div>
                    </div>
                    <div class="c-card__main-content">
                        <a href="">Compact icon - Shakespeare in Love</a>
                    </div>
                </div>
            </div>
        `,
    }),
};
