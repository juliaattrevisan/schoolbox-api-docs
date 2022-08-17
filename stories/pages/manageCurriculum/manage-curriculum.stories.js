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
    },
    component: ManageCurriculum,
    decorators: [
        () => ({
            template: `
            <div id="container">
                <div id="content">
                    <div class="row">
                        <div class="small-12 columns island">
                            <section>
                                <fieldset class="content">
                                    <div class="small-12 columns">
                                        <story />
                                    </div>
                                </fieldset>
                            </section>
                        </div>
                    </div>
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

// export const LoadError = Template.bind({});
// LoadError.parameters = {
//     msw: {
//         handlers: [
//             rest.get(dataEndpoint, (req, res, ctx) => {
//                 return res(
//                     ctx.status(500) //to simulate an error
//                 )
//             }),
//             ...mswHandlers,
//         ]
//     }
// }

// export const PreSelectedCodes = Template.bind({});
// PreSelectedCodes.args = {
//     ...Default.args,
//     selectedCodes: [
//         { "id":26, "name":"Generate solutions to challenges and learning area tasks", "code":"GCICT-GSCLAT", "description":"Suspendisse aliquet porta ligula ac vulputate. Ut vulputate, erat ac dapibus tempus, urna arcu imperdiet justo, aliquam sollicitudin lorem enim quis sapien. Donec id porttitor nibh, nec ullamcorper metus. Quisque nec tempor nibh. Nullam sed mi id quam condimentum rutrum. Nulla fringilla nulla vehicula scelerisque pharetra. Curabitur at turpis suscipit, commodo leo et, gravida sem. Etiam eleifend tellus at ante aliquet, maximus maximus turpis egestas.", "reference":null, "children":[], "area":null, "level":null, "breadcrumb":"General Capabilities > Information and Communication Technology Capability > Creating with ICT" },
//         { "id":45, "name":"Challenge stereotypes and prejudices", "code":"GCIU-CSP", "description":"Integer varius porttitor volutpat. Integer rhoncus magna nec metus pulvinar ornare. Proin non venenatis ante. Maecenas pretium scelerisque massa eu molestie. Aenean a convallis nibh, at pellentesque leo. Ut vehicula accumsan nulla, a scelerisque nisl rutrum sed. Proin dictum eros diam, vel tincidunt tellus tincidunt ac. Ut dapibus lorem nec fringilla tempus.", "reference":null, "children":[], "area":null, "level":null, "breadcrumb":"General Capabilities > Intercultural Understanding > Reflecting on intercultural experiences and taking responsibility" }
//     ],
// }

// export const readOnly = Template.bind({});
// readOnly.args = {
//     ...Default.args,
//     selectedCodes: [
//         { "id":106, "name":"Comprehend texts", "code":"CMP-TEX", "description":"", "reference":null, "children":[], "area":null, "level":null, "breadcrumb":"General Capabilities > Literacy > Comprehending texts through listening, reading and viewing" },
//         { "id":107, "name":"Navigate, read and view learning area texts", "code":"GCL-NRVLAT", "description":"", "reference":null, "children":[], "area":null, "level":null, "breadcrumb":"General Capabilities > Literacy > Comprehending texts through listening, reading and viewing" },
//         { "id":108, "name":"Listen and respond to learning area texts", "code":"GCL-LRLAT", "description":"", "reference":null, "children":[ ], "area":null, "level":null, "breadcrumb":"General Capabilities > Literacy > Comprehending texts through listening, reading and viewing" },
//     ],
//     isReadOnly: true,
// }

// export const readOnlyWithNoPreSelectedCodes = Template.bind({});
// readOnlyWithNoPreSelectedCodes.args = {
//     ...Default.args,
//     selectedCodes: [],
//     isReadOnly: true,
// }

// export const SearchExamples = Template.bind({});
// SearchExamples.decorators = [
//     () => ({
//         template: `
//         <story />
//         <p style="margin-top: 1rem;">Paste Codes: GCCCT-ALR, GCCCT-DCDCA, GCCCT-EPO, GCN-RUPR, GCN-IPR, GCN-APR</p>
//         <p>Search Terms: self-awareness</p>
//         `
//     })
// ];

// export const VicCurriculum = Template.bind({});
// VicCurriculum.parameters = {
//     msw: {
//         handlers: [
//             rest.get(dataEndpoint, (req, res, ctx) => {
//                 return res(
//                     ctx.json(vicCurriculumData)
//                 )
//             }),
//             ...mswHandlers,
//         ]
//     }
// }

// export const NswCurriculum = Template.bind({});
// NswCurriculum.parameters = {
//     msw: {
//         handlers: [
//             rest.get(dataEndpoint, (req, res, ctx) => {
//                 return res(
//                     ctx.json(nswCurriculumData)
//                 )
//             }),
//             ...mswHandlers,
//         ]
//     }
// }