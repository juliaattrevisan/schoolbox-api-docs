
import SbHeader from './header.vue';

export default {
    title: 'UI/Header',
    component: SbHeader,
};

const Template = (args, { argTypes }) => ({
    components: { SbHeader },
    template:
        `
        <sb-header />
        `,
});

export const Default = Template.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};