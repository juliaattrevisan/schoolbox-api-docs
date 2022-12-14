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

const OneRecentActivityTemplate = (args, { argTypes }) => ({
    components: { PopOutMenuMarkbook },
    setup() {
        return { args };
    },
    template:
        `
        <pop-out-menu-markbook v-bind="args" />
        `,
});

const NoRecentActivityTemplate = (args, { argTypes }) => ({
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
    historyItems: [
        {
            type: 'view',
            effector: {
                id: 60,
                name: 'Jamal Ali'
            },
            date: {
                datetime: '2022-12-20 17:28:13',
                short: 'Dec 20, 2022 5:28pm',
                relative: '2 seconds ago',
            },
        },
        {
            type: 'view',
            effector: {
                id: 30,
                name: 'Ruth Ali'
            },
            date: {
                datetime: '2022-12-20 17:28:13',
                short: 'Dec 20, 2022 5:18pm',
                relative: '10 minutes ago',
            },
        },
        {
            type: 'feedback',
            effector: {
                id: 1,
                name: 'Jack Coles'
            },
            date: {
                datetime: '2022-12-20 17:28:13',
                short: 'Dec 20, 2022 5:08pm',
                relative: '20 minutes ago',
            },
        },
        {
            type: 'response',
            effector: {
                id: 1,
                name: 'Jamal Ali'
            },
            date: {
                datetime: '2022-12-20 17:28:13',
                short: 'Dec 20, 2022 4:48pm',
                relative: '40 minutes ago',
            },
        },
        {
            type: 'feedback',
            effector: {
                id: 1,
                name: 'Jack Coles'
            },
            date: {
                datetime: '2022-12-20 17:28:13',
                short: 'Dec 20, 2022 4:28pm',
                relative: '1 hour ago',
            },
        },
        {
            type: 'response',
            effector: {
                id: 1,
                name: 'Jamal Ali'
            },
            date: {
                datetime: '2022-12-20 17:28:13',
                short: 'Dec 20, 2022 1:28pm',
                relative: '4 hours ago',
            },
        },
        {
            type: 'response',
            effector: {
                id: 1,
                name: 'Jamal Ali'
            },
            date: {
                datetime: '2022-12-20 17:28:13',
                short: 'Dec 20, 2022 12:18pm',
                relative: '5 hours ago',
            },
        },
        {
            type: 'response',
            effector: {
                id: 1,
                name: 'Jamal Ali'
            },
            date: {
                datetime: '2022-12-20 17:28:13',
                short: 'Dec 20, 2022 11:18am',
                relative: '6 hours ago',
            },
        },
        {
            type: 'response',
            effector: {
                id: 1,
                name: 'Jamal Ali'
            },
            date: {
                datetime: '2022-12-20 17:28:13',
                short: 'Dec 20, 2022 10:18am',
                relative: '7 hours ago',
            },
        },
    ]
}

export const OneRecentActivity = OneRecentActivityTemplate.bind({});
OneRecentActivity.parameters = {
    controls: { hideNoControlsWarning: true },
};
OneRecentActivity.args = {
    historyItems: [
        {
            type: 'response',
            effector: {
                id: 60,
                name: 'Jamal Ali'
            },
            date: {
                datetime: '2022-12-20 15:28:13',
                short: 'Dec 20, 2022 5:34pm',
                relative: '2 seconds ago',
            },
        },
    ]
}

export const NoRecentActivity = NoRecentActivityTemplate.bind({});
NoRecentActivity.parameters = {
    controls: { hideNoControlsWarning: true },
};
