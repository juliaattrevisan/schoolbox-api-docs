/**
 * To select Curriculum codes
 */

import CurriculumSelector from '../../library/Schoolbox/Curriculum/templates/vue/curriculum-selector.vue';

export default {
    // args: {
    //     groupDataEndpoint: groupDataEndpoint,
    // },
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
        // msw: {
        //     handlers: mswHandlers
        // },
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