
import DropDown from './drop-down.vue';

export default {
    title: 'Animations/Badge Awards/Drop Down',
    component: DropDown,
    parameters: {
        docs: {
            description: {
                component: '_Drop Down_ badge award animation',
            },
        },
    },
};

const Template = (args, { argTypes }) => ({
    props: Object.keys(argTypes),
    components: { DropDown },
    template:
        `
        <drop-down />
        `,
});

export const Default = Template.bind({});