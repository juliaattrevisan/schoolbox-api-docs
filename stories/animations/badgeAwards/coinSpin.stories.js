
import CoinSpin from './coin-spin.vue';

export default {
    title: 'Animations/Badge Awards/Coin Spin',
    component: CoinSpin,
    parameters: {
        docs: {
            description: {
                component: '_Coin Spin_ badge award animation',
            },
        },
    },
};

const Template = (args, { argTypes }) => ({
    props: Object.keys(argTypes),
    components: { CoinSpin },
    template:
        `
        <coin-spin />
        `,
});

export const Default = Template.bind({});