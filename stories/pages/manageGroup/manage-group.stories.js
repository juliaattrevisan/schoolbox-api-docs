
import ManageGroup from '../../../library/Schoolbox/Group/Manage/vue/manage-group';
import { rest } from 'msw';
import { data } from './dataManageGroup';

const groupDataEndpoint = '/groupData';
const removeMembersEndpoint = '/removeMembers';
const updateMembersEndpoint = '/updateMembers';
const addMembersEndpoint = '/addMembers';
const updateSettingsEndpoint = '/updateSettings';
const disbandGroupEndpoint = '/disbandGroup';
const sendEmailEndpoint = '/sendEmail';

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
                    {"id":201,"firstName":"Valentine","lastName":"Holmes","userId":200,"accessType":"read","role":"Senior Student","campus":"Senior","yearLevel":"Year 11","house":"Gresham (Green)"},
                    {"id":202,"firstName":"Kurt","lastName":"Capewell","userId":201,"accessType":"read","role":"Senior Student","campus":"Senior","yearLevel":"Year 11","house":"Gresham (Green)"},
                    {"id":203,"firstName":"Kalyn","lastName":"Ponga","userId":202,"accessType":"read","role":"Senior Student","campus":"Senior","yearLevel":"Year 11","house":"Gresham (Green)"},
                    {"id":204,"firstName":"Ben","lastName":"Hunt","userId":203,"accessType":"read","role":"Senior Student","campus":"Senior","yearLevel":"Year 11","house":"Gresham (Green)"},
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
        sendEmailEndpoint: sendEmailEndpoint,
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

export const NoAccessSet = Template.bind({});
NoAccessSet.parameters = {
    msw: {
        handlers: [
            rest.get(groupDataEndpoint, (req, res, ctx) => {
                return res(
                    ctx.json({
                        ...data,
                        settings: {
                            access: '',
                            isNotificationsEnabled: true,
                            isForceFollow: false,
                        },
                    })
                )
            }),
            ...mswHandlers,
        ]
    }
}
