import RightDrawer from './right-drawer.vue';

// More on default export: https://storybook.js.org/docs/vue/writing-stories/introduction#default-export
export default {
    title: 'Components/Drawers/Right Drawer',
    component: RightDrawer,
    // More on argTypes: https://storybook.js.org/docs/vue/api/argtypes
    argTypes: {
        default: {
            control: { type: 'text' },
            defaultValue: '<strong>Bottom drawer content goes here</strong>',
        },
    },
};

// More on component templates: https://storybook.js.org/docs/vue/writing-stories/introduction#using-args
const Template = (args, { argTypes }) => ({
    components: { RightDrawer },
    setup() {
        //👇 The args will now be passed down to the template
        return { args };
    },
    template:
        `
        <right-drawer v-bind="args">
            ${args.default}
        </right-drawer>
        `,
});

export const Default = Template.bind({});
// More on args: https://storybook.js.org/docs/vue/writing-stories/args
Default.args = {
    showDrawer: false,
};