import SmallCard from "../../vue/components/card/small-card.vue";
import UserPhoto from "../../vue/components/userPhoto/user-photo.vue";

export default {
    title: "Cards/Small",
    component: SmallCard,
};

const DefaultTemplate = (args, { argTypes }) => ({
    components: { SmallCard, UserPhoto },
    template: `
        <small-card>
            <user-photo></user-photo>
            <div class="c-card__user-icon"></div>
            <div class="c-card__details">
                <div class="c-card__text">Jane submitted on time</div>
                <div class="c-card__subtext">3:47PM Tuesday 13th July 2022</div>
            </div>
        </small-card>
    `,
});

const NoDateTemplate = (args, { argTypes }) => ({
    components: { SmallCard, UserPhoto },
    template: `
        <small-card>
            <user-photo></user-photo>
            <div class="c-card__user-icon"></div>
            <div class="c-card__details">
                <div class="c-card__text">Jane submitted on time</div>
            </div>
        </small-card>
    `,
});

export const Default = DefaultTemplate.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const NoDate = NoDateTemplate.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};
