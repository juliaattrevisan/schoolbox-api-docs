
import SwingDown from './swing-down.vue';

export default {
    title: 'Animations/Badge Awards/Swing Down',
    component: SwingDown,
    parameters: {
        docs: {
            description: {
                component: '_Swing Down_ badge award animation',
            },
        },
    },
};

const Template = (args, { argTypes }) => ({
    components: { SwingDown },
    template:
        `
        <swing-down />
        `,
});

export const Default = Template.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
    chromatic: { disableSnapshot: true }, // disables Chromatic's snapshotting, as animation will always trigger visual changes
};