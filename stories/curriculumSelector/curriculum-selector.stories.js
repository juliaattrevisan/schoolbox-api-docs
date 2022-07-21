/**
 * To select Curriculum codes
 */

import CurriculumSelector from '../../library/Schoolbox/Curriculum/templates/vue/curriculum-selector.vue';
import { rest } from 'msw';
import { data } from './dataCurriculumSelector';

const dataEndpoint = '/curriculumData'; 

const mswHandlers = [
    rest.get(dataEndpoint, (req, res, ctx) => {
        return res( //we grab an endpoint and then modify the data before it's returned
            ctx.delay(1000),
            ctx.json(data)
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