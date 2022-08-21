/**
 * To select Curriculum codes to apply to a Due Work, Learning Moments, Badges etc
 */

import ManageCurriculum from '@library/Schoolbox/Curriculum/Manage/vue/manage-curriculum.vue';
import { rest } from 'msw';
import { data as allCurriculumData } from '../../curriculumSelector/dataCurriculumSelector';
// import { data as vicCurriculumData } from '../../curriculumSelector/dataVicCurriculum';
// import { data as nswCurriculumData } from '../../curriculumSelector/dataNswCurriculum';


const dataEndpoint = '/curriculumData';

const mswHandlers = [
    rest.get(dataEndpoint, (req, res, ctx) => {
        return res(
            ctx.json(allCurriculumData)
        )
    }),
];

export default {
    args: {
        dataEndpoint: dataEndpoint,
        isReadOnly: false,
    },
    component: ManageCurriculum,
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
                component: 'To browse and manage Curriculum',
            },
        },
        msw: {
            handlers: mswHandlers
        },
    },
    title: 'Pages/ManageCurriculum',
};

const Template = (args, { argTypes }) => ({
    components: { ManageCurriculum },
    setup() {
        return { args };
    },
    template:
        `
        <manage-curriculum v-bind="args" />
        `,
});

export const Default = Template.bind({});

export const LoadError = Template.bind({});
LoadError.parameters = {
    msw: {
        handlers: [
            rest.get(dataEndpoint, (req, res, ctx) => {
                return res(
                    ctx.status(500) //to simulate an error
                )
            }),
            ...mswHandlers,
        ]
    }
}

export const readOnly = Template.bind({});
readOnly.args = {
    ...Default.args,
    isReadOnly: true,
}

export const editModal = Template.bind({});
editModal.decorators = [
    () => ({
        template: `
        <story />
        <p style="margin: 1rem;"><em>To view a node with full info, navigate to Victorian Curriculum > The Arts > Dance > Level A > Explore and Express Ideas > Experience dance, safe movement possibilities and move their body parts intentionally in response to stimulus</em></p>
        `
    })
];