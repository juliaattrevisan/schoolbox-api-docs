import PopOutMenuMarkbook from '../../vue/components/menu/pop-out-menu-markbook.vue';

export default {
    title: 'Menu/PopOutMenuMarkbook',
    component: PopOutMenuMarkbook,
};

const DefaultTemplate = (args, { argTypes }) => ({
    components: { PopOutMenuMarkbook },
    setup() {
        return { args };
    },
    template:
        `
        <pop-out-menu-markbook v-bind="args" />
        `,
});


export const Default = DefaultTemplate.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};
Default.args = {
};
