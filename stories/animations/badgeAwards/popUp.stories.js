
import PopUp from './pop-up.vue';

export default {
    title: 'Animations/Badge Awards/Pop Up',
    component: PopUp,
    parameters: {
        docs: {
            description: {
                component: '_Pop Up_ badge award animation',
            },
        },
    },
};

const Template = (args, { argTypes }) => ({
    components: { PopUp },
    template:
        `
        <pop-up />
        `,
});

export const Default = Template.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};