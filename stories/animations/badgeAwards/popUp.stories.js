
import PopUp from './pop-up.vue';

// More on default export: https://storybook.js.org/docs/vue/writing-stories/introduction#default-export
export default {
    title: 'Schoolbox/Animations/Badge Awards/Pop Up',
    component: PopUp,
    // wrap the component in some html: https://storybook.js.org/docs/vue/writing-stories/introduction#using-decorators
    // decorators: [() => ({ template: '<div style="width: 80%; margin: 0 auto;"><story /></div>' })],
    parameters: {
        docs: {
            description: {
                component: 'Some component _markdown_',
            },
        },
    },
};

// More on component templates: https://storybook.js.org/docs/vue/writing-stories/introduction#using-args
const Template = (args, { argTypes }) => ({
    props: Object.keys(argTypes),
    components: { PopUp },
    template:
        `
        <pop-up />
        `,
});

export const Default = Template.bind({});