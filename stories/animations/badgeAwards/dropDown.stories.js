
import DropDown from './drop-down.vue';

export default {
    title: 'Animations/Badge Awards/Drop Down',
    component: DropDown,
    parameters: {
        docs: {
            description: {
                component: '_Drop Down_ badge award animation',
            },
        },
    },
};

const Template = (args, { argTypes }) => ({
    components: { DropDown },
    template:
        `
        <drop-down />
        `,
});

export const Default = Template.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
    chromatic: { disableSnapshot: true }, // disables Chromatic's snapshotting, as animation will always trigger visual changes
};