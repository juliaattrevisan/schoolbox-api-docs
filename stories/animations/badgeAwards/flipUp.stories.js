
import FlipUp from './flip-up.vue';

export default {
    title: 'Animations/Badge Awards/Flip Up',
    component: FlipUp,
    parameters: {
        docs: {
            description: {
                component: '_Flip up_ badge award animation',
            },
        },
    },
};

const Template = (args, { argTypes }) => ({
    components: { FlipUp },
    template:
        `
        <flip-up />
        `,
});

export const Default = Template.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
    chromatic: { disableSnapshot: true }, // disables Chromatic's snapshotting, as animation will always trigger visual changes
};