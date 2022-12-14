
import MarkbookPopOut from '../../../library/Schoolbox/Learning/Markbook/templates/vue/markbook-pop-out.vue';

export default {
    title: 'Pages/ClassMarkbook/PopOut',
    component: MarkbookPopOut,
};

const Template = (args, { argTypes }) => ({
    components: { MarkbookPopOut },
    template:
        `
        <markbook-pop-out />
        `,
});

export const Default = Template.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};