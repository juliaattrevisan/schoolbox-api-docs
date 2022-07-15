
import ManageGroup from '../../../templates/core/group/vue/manage-group';
import { rest } from 'msw';
import { data } from './dataManageGroup';
// import { largeData } from '../../classSelector/largeDataClassSelectoreDataClassSelector';

const groupDataEndpoint = '/groupData'; 
const removeMembersEndpoint = '/removeMembers';
const updateMembersEndpoint = '/updateMembers';
const addMembersEndpoint = '/addMembers';
const updateSettingsEndpoint = '/updateSettings';
const disbandGroupEndpoint = '/disbandGroup';

const mswHandlers = [
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
                    {"id":201,"firstName":"John","lastName":"Smith","userId":200,"accessType":"read","meta":"Year 11, Senior Campus"},
                    {"id":202,"firstName":"Jane","lastName":"Cooper","userId":201,"accessType":"read","meta":"Year 11, Senior Campus"},
                    {"id":203,"firstName":"Candy","lastName":"Melon","userId":202,"accessType":"read","meta":"Teacher"},
                    {"id":204,"firstName":"Guava","lastName":"Apple","userId":203,"accessType":"read","meta":"Year 11, Senior Campus"},
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
    rest.post(disbandGroupEndpoint, (req, res, ctx) => {
        return res(
            ctx.delay(2000),
            ctx.json({
                success: true,
            })
        )
    }), 
]

export default {
    args: {
        groupDataEndpoint: groupDataEndpoint,
        removeMembersEndpoint: removeMembersEndpoint,
        updateMembersEndpoint: updateMembersEndpoint,
        addMembersEndpoint: addMembersEndpoint,
        updateSettingsEndpoint: updateSettingsEndpoint,
        disbandGroupEndpoint: disbandGroupEndpoint,
    },
    component: ManageGroup,
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
    parameters: {
        controls: { hideNoControlsWarning: true },
        docs: {
            description: {
                component: 'All the functionality to be able to manage a group',
            },
        },
        msw: {
            handlers: mswHandlers
        },
    },
    title: 'Pages/ManageGroup',
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


export const Default = Template.bind({});

export const LoadError = Template.bind({});
LoadError.parameters = {
    msw: {
        handlers: [ 
            rest.get(groupDataEndpoint, (req, res, ctx) => {
                return res( 
                    ctx.status(500) //to simulate an error
                ) 
            }),
            ...mswHandlers,
        ]
    }
}

export const NoMembers = Template.bind({});
NoMembers.parameters = {
    msw: {
        handlers: [
            rest.get(groupDataEndpoint, (req, res, ctx) => {
                return res( 
                    ctx.delay(1000),
                    ctx.json({
                        ...data, 
                        members: [], //start with no members
                    })
                ) 
            }),
            ...mswHandlers,
        ]
    }
}

export const NoGroupAdmins = Template.bind({});
NoGroupAdmins.parameters = {
    msw: {
        handlers: [
            rest.get(groupDataEndpoint, (req, res, ctx) => {
                return res( 
                    ctx.delay(1000),
                    ctx.json({
                        ...data, 
                        members: [ ...data.members.filter((member) => member.accessType !== 'admin')], //to pull out the admins
                    })
                ) 
            }),
            ...mswHandlers,
        ]
    }
}