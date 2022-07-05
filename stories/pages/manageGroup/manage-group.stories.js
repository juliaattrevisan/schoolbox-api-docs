
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
    decorators: [
        () => ({ 
            template: `
                <div id="container">
                    <div id="content">
                        <story />
                    </div>
                </div>
            `
        })
    ],
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

const getMembersEndpoint = '/getMembers'; //just using any active endpoint for a connection
const removeMembersEndpoint = '/removeMembers';

export const Default = Template.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
    msw: {
        handlers: [
            rest.get(getMembersEndpoint, (req, res, ctx) => {
                return res( //we grab an endpoint and then modify the data before it's returned
                    ctx.delay(1000),
                    ctx.json(data)
                ) 
            }),
            rest.post(removeMembersEndpoint, (req, res, ctx) => {
                return res(
                    ctx.delay(3000),
                    ctx.json({
                       success: true,
                    })
                )
            }), 
        ]
    },
}
Default.args = {
    getMembersEndpoint: getMembersEndpoint,
    removeMembersEndpoint: removeMembersEndpoint,
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
