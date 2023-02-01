
import PopOut from "../../../vue/components/popOut/pop-out";
import PopOutMenuMarkbook from '../../../vue/components/menu/pop-out-menu-markbook.vue';

export default {
    title: 'Pages/ClassMarkbook/PopOut',
    component: PopOutMenuMarkbook,
};

const Template = (args, { argTypes }) => ({
    components: { PopOut, PopOutMenuMarkbook },
    template:
        `
        <pop-out />
        `,
});

export const Default = Template.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};
