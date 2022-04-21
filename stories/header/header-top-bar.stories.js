
import SbHeaderTopBar from '../../vue/components/header/header-top-bar';

export default {
    title: 'IN PROGRESS/Header/Components/TopBar',
    component: SbHeaderTopBar,
};

const Template = (args, { argTypes }) => ({
    components: { SbHeaderTopBar },
    template:
        `
        <sb-header-top-bar/>
        `,
});

export const Default = Template.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};