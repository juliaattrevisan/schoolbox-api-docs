import BottomDrawer from './bottom-drawer.vue';

// More on default export: https://storybook.js.org/docs/vue/writing-stories/introduction#default-export
export default {
    title: 'Components/Bottom Drawer',
    component: BottomDrawer,
    // More on argTypes: https://storybook.js.org/docs/vue/api/argtypes
    argTypes: {
        showDrawer: false,
        default: {
            control: {
                type: 'text'
            },
            defaultValue: '<strong>My slot prop</strong>',
            meta_description: 'slot content',
            table: {
                type: {
                    summary: null,
                }
            },
        },
    },
    //wrap the component in some html: https://storybook.js.org/docs/vue/writing-stories/introduction#using-decorators
    decorators: [() => ({ template: '<div style="width: 80%; margin: 0 auto;"><story /></div>' })],
};

// More on component templates: https://storybook.js.org/docs/vue/writing-stories/introduction#using-args
const Template = (args, { argTypes }) => ({
    props: Object.keys(argTypes),
    components: { BottomDrawer },
    template:
        `
        <bottom-drawer v-bind="$props">
            <template v-if="${'default' in args}" v-slot:default>${args.default}</template>
        </bottom-drawer>
        `,
});

export const Default = Template.bind({});
// More on args: https://storybook.js.org/docs/vue/writing-stories/args
Default.args = {
    showDrawer: true,
};