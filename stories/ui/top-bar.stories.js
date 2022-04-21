
import SbTopBar from '../../vue/components/header/top-bar';

export default {
    title: 'IN PROGRESS/UI/TopBar',
    component: SbTopBar,
};

const Template = (args, { argTypes }) => ({
    components: { SbTopBar },
    template:
        `
        <sb-top-bar/>
        `,
});

export const Default = Template.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};