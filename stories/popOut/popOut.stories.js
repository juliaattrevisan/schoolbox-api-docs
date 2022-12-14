import PopOut from '../../vue/components/popOut/pop-out.vue';

export default {
    title: 'PopOut/PopOut',
    component: PopOut,
};

const DefaultTemplate = (args, { argTypes }) => ({
    components: { PopOut },
    template:
        `
        <pop-out />
        `,
});

export const Default = DefaultTemplate.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};
