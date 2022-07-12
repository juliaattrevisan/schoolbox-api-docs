
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

const groupDataEndpoint = '/groupData'; 
const removeMembersEndpoint = '/removeMembers';
const updateMembersEndpoint = '/updateMembers';
const addMembersEndpoint = '/addMembers';
const updateSettingsEndpoint = '/updateSettings';

export const Default = Template.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
    msw: {
        handlers: [
            rest.get(groupDataEndpoint, (req, res, ctx) => {
                return res( //we grab an endpoint and then modify the data before it's returned
                    ctx.delay(1000),
                    ctx.json(data)
                ) 
            }),
            rest.delete(removeMembersEndpoint, (req, res, ctx) => {
                return res(
                    ctx.delay(2000),
                    ctx.json({
                       success: true,
                    })
                )
            }), 
            rest.patch(updateMembersEndpoint, (req, res, ctx) => {
                return res(
                    ctx.delay(1000),
                    ctx.json({
                       success: true,
                    })
                )
            }), 
            rest.post(addMembersEndpoint, (req, res, ctx) => {
                return res(
                    ctx.delay(1000),
                    ctx.json({
                        newMembers: [
                            {"id":201,"firstName":"John","lastName":"Smith","userId":200,"accessType":"read","meta":"year 11"},
                            {"id":202,"firstName":"Jane","lastName":"Cooper","userId":201,"accessType":"read","meta":"year 11"},
                            {"id":203,"firstName":"Candy","lastName":"Melon","userId":202,"accessType":"read","meta":"teacher"},
                            {"id":204,"firstName":"Guava","lastName":"Apple","userId":203,"accessType":"read","meta":"year 11"},
                        ],
                        success: true,
                    })
                )
            }), 
            rest.post(updateSettingsEndpoint, (req, res, ctx) => {
                return res(
                    ctx.delay(2000),
                    ctx.json({
                        success: true,
                    })
                )
            }), 
        ]
    },
}
Default.args = {
    groupDataEndpoint: groupDataEndpoint,
    removeMembersEndpoint: removeMembersEndpoint,
    updateMembersEndpoint: updateMembersEndpoint,
    addMembersEndpoint: addMembersEndpoint,
    updateSettingsEndpoint: updateSettingsEndpoint,
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
