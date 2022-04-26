
import SbHeaderTopBar from '../../vue/components/header/header-top-bar';

export default {
    title: 'IN PROGRESS/Header/Components/TopBar',
    component: SbHeaderTopBar,
};

const Template = (args, { argTypes }) => ({
    components: { SbHeaderTopBar },
    setup () {
        return { args }; //the args will now be passed down to the template
    },
    template:
        `<sb-header-top-bar v-bind="args" />`,
});

export const Default = Template.bind({});

export const Notifications = Template.bind({});
Notifications.args = {
    unreadNotifications: 32,
};