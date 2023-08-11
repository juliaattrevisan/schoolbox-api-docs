// import SmallCard from "../../../vue/components/card/small-card.vue";
// import UserPhoto from "../../vue/components/userPhoto/user-photo.vue";

// export default {
//     title: "Components/Cards/Small",
//     component: SmallCard,
// };

// const DefaultTemplate = (args, { argTypes }) => ({
//     components: { SmallCard, UserPhoto },
//     template: `
//         <small-card>
//             <user-photo></user-photo>
//             <div class="c-card__user-icon"></div>
//             <div class="c-card__details">
//                 <div class="c-card__text">Jane submitted on time</div>
//                 <div class="c-card__subtext">3:47PM Tuesday 13th July 2022</div>
//             </div>
//         </small-card>
//     `,
// });

// const NoDateTemplate = (args, { argTypes }) => ({
//     components: { SmallCard, UserPhoto },
//     template: `
//         <small-card>
//             <user-photo></user-photo>
//             <div class="c-card__user-icon"></div>
//             <div class="c-card__details">
//                 <div class="c-card__text">Jane submitted on time</div>
//             </div>
//         </small-card>
//     `,
// });

// export const Default = DefaultTemplate.bind({});
// Default.parameters = {
//     controls: { hideNoControlsWarning: true },
// };

// export const NoDate = NoDateTemplate.bind({});
// Default.parameters = {
//     controls: { hideNoControlsWarning: true },
// };

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
                    <div class="c-card__primary-img c-img c-img--s">
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
                    <div class="c-card__primary-img c-img c-img--s">
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
