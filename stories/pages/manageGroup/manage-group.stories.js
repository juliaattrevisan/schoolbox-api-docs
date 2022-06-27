
import ManageGroup from '../../../templates/core/group/vue/manage-group';
import { rest } from 'msw';
import { data } from './dataManageGroup';
// import { largeData } from '../../classSelector/largeDataClassSelectoreDataClassSelector';

export default {
    title: 'Pages/ManageGroup',
    component: ManageGroup,
    parameters: {
        docs: {
            description: {
                component: 'All the functionality to be able to manage a group',
            },
        },
    },
    decorators: [() => ({ 
    template: `
        <story />
    `
    })],
};


const Template = (args, { argTypes }) => ({
    components: { ManageGroup },
    setup() {
        return { args };
    },
    template:
        `
        <manage-group v-bind="args" />
        `,
});

const endpoint = 'https://swapi.dev/api/films/'; //just using any active endpoint for a connection

export const Default = Template.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
    msw: {
        handlers: [
            rest.get(endpoint, (req, res, ctx) => {
                return res(ctx.json(data)) //we grab an endpoint and then modify the data before it's returned
            }), 
        ]
    },
}
Default.args = {
    endpoint: endpoint,
}

// export const LoadError = Template.bind({});
// LoadError.parameters = {
//     controls: { hideNoControlsWarning: true },
//     msw: {
//         handlers: {
//             auth: rest.get(endpoint, (req, res, ctx) => {
//                 return res(ctx.status(403))
//             }),
//         }
//     }
// }
// LoadError.args = {
//     endpoint: endpoint,
// }
