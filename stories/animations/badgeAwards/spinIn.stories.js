
import SpinIn from './spin-in.vue';

export default {
    title: 'Animations/Badge Awards/Spin In',
    component: SpinIn,
    parameters: {
        docs: {
            description: {
                component: '_Spin In_ badge award animation',
            },
        },
    },
};

const Template = (args, { argTypes }) => ({
    components: { SpinIn },
    template:
        `
        <spin-in />
        `,
});

export const Default = Template.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
    chromatic: { disableSnapshot: true }, // disables Chromatic's snapshotting, as animation will always trigger visual changes
};