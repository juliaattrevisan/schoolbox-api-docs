import MenuDivider from '../../vue/components/menu/pop-out-menu-divider.vue';

export default {
    title: 'Menu/MenuDivider',
    component: MenuDivider,
};

const DefaultTemplate = (args, { argTypes }) => ({
    components: { MenuDivider },
    template:
        `
        <pop-out-menu-divider />
        `,
});

export const Default = DefaultTemplate.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};
