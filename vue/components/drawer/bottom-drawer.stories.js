import BottomDrawer from './bottom-drawer.vue';

// More on default export: https://storybook.js.org/docs/vue/writing-stories/introduction#default-export
export default {
    title: 'Components/Drawers/Bottom Drawer',
    component: BottomDrawer,
    // More on argTypes: https://storybook.js.org/docs/vue/api/argtypes
    argTypes: {
        showDrawer: { 
            type: { required: true },
        },
        isAlwaysAnchored: { 
            type: { required: false },
        },
        unanchorWidth: { 
            type: { required: false },
        },
        default: {
            control: { type: 'text' },
            defaultValue: '<strong>Bottom drawer content goes here</strong>',
        },
    },
    //wrap the component in some html: https://storybook.js.org/docs/vue/writing-stories/introduction#using-decorators
    decorators: [() => ({ template: '<div style="width: 80%; margin: 0 auto;"><story /></div>' })],
};

// More on component templates: https://storybook.js.org/docs/vue/writing-stories/introduction#using-args
const Template = (args, { argTypes }) => ({
    components: { BottomDrawer },
    setup() {
        //👇 The args will now be passed down to the template
        return { args };
    },
    template:
        `
        <bottom-drawer v-bind="args">
            ${args.default}
        </bottom-drawer>
        `,
});

export const Default = Template.bind({});
// More on args: https://storybook.js.org/docs/vue/writing-stories/args
Default.args = {
    showDrawer: false,
};

export const Unanchored = Template.bind({});
Unanchored.args = {
    showDrawer: true,
    isAlwaysAnchored: false,
    unanchorWidth: 2000,
};