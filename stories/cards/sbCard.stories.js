import SbCard from "../../vue/components/card/sb-card.vue";

export default {
    title: "Cards/SbCard",
    component: SbCard,
};

const DefaultTemplate = (args, { argTypes }) => ({
    components: { SbCard },
    template: `
        <sb-card>
        </sb-card>
    `,
});

export const Default = DefaultTemplate.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};
