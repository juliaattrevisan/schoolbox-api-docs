
import ClassSelector from '../../library/Schoolbox/Assessment/Course/templates/vue/class-selector';
import { rest } from 'msw';
import { data } from './dataClassSelector';

export default {
    title: 'IN PROGRESS/ClassSelector',
    component: ClassSelector,
    decorators: [() => ({ 
    template: `
    <div class="row">
        <div class="small-12 island" style="margin-top: 1rem">
            <section>
                <fieldset class="content">
                    <legend>1. Course</legend>
                    <div class="meta small-12 column">
                        <p>Select the course that is to be imported, and one or more classes as the target destination.</p>
                    </div>
                    <div class="small-5 medium-5 large-3 columns">
                        <label>Course</label>
                        <input type="text" />
                    </div>
                    <div class="small-2 medium-2 large-1 columns text-center">
                        <p class="course-import icon-next"></p>
                    </div>
                    <div class="small-5 medium-5 large-8 columns">
                        <story />
                    </div>
                </fieldset>
            </section>
        </div>
    </div>`
    })],
};


const Template = (args, { argTypes }) => ({
    components: { ClassSelector },
    setup() {
        //👇 The args will now be passed down to the template
        return { args };
    },
    template:
        `
        <class-selector v-bind="args" />
        `,
});

export const Default = Template.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
    msw: {
        handlers: [
            rest.get('https://swapi.dev/api/films/', (req, res, ctx) => {
                return res(ctx.json(data)) //we grab an endpoint and then modify the data before it's returned
            }), 
        ]
    },
}

Default.args = {
    endpoint: 'https://swapi.dev/api/films/',
}