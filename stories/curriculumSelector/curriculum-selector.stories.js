/**
 * To select Curriculum codes
 */

import CurriculumSelector from '../../library/Schoolbox/Curriculum/templates/vue/curriculum-selector.vue';
import { rest } from 'msw';
import { data as allCurriculumData } from './dataCurriculumSelector';
import { data as vicCurriculumData } from './dataVicCurriculum';

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
    component: CurriculumSelector,
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
                component: 'To browse and select Curriculum',
            },
        },
        msw: {
            handlers: mswHandlers
        },
    },
    title: 'Components/Selectors/CurriculumSelector',
};

const Template = (args, { argTypes }) => ({
    components: { CurriculumSelector },
    setup() {
        return { args };
    },
    template:
        `
        <curriculum-selector v-bind="args" />
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

export const SingleCurriculumGroup = Template.bind({});
SingleCurriculumGroup.parameters = {
    msw: {
        handlers: [ 
            rest.get(dataEndpoint, (req, res, ctx) => {
                return res( 
                    ctx.json(vicCurriculumData)
                ) 
            }),
            ...mswHandlers,
        ]
    }
}

export const SearchExamples = Template.bind({});
SearchExamples.decorators = [
    () => ({ 
        template: `
        <story />
        <p>Paste Codes: GCCCT-ALR, GCCCT-DCDCA, GCCCT-EPO, GCN-RUPR, GCN-IPR, GCN-APR</p>
        <p>Search Terms: self-awareness</p>
        `
    })
]