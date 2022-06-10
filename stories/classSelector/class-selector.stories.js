
import ClassSelector from '../../library/Schoolbox/Assessment/Course/templates/vue/class-selector';
import { rest } from 'msw';
import { data } from './dataClassSelector';
import { largeData } from './largeDataClassSelector';

export default {
    title: 'Components/Selectors/ClassSelector',
    component: ClassSelector,
    parameters: {
        docs: {
            description: {
                component: 'To choose the classes a course will be imported to',
            },
        },
    },
    decorators: [() => ({ 
    template: `
    <div class="row" style="padding: 0 1rem">
        <div class="small-12 island" style="margin-top: 2rem">
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
        return { args };
    },
    template:
        `
        <class-selector v-bind="args" />
        `,
});

const endpoint = 'https://swapi.dev/api/films/'; //just using any active endpoint for a connection

export const Default = Template.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
    msw: {
        handlers: [
            rest.get(endpoint, (req, res, ctx) => {
                return res(ctx.json(data)) //we grab an endpoint and then modify the data before it's returned
            }), 
        ]
    },
}
Default.args = {
    endpoint: endpoint,
}

export const LoadError = Template.bind({});
LoadError.parameters = {
    controls: { hideNoControlsWarning: true },
    msw: {
        handlers: {
            auth: rest.get(endpoint, (req, res, ctx) => {
                return res(ctx.status(403))
            }),
        }
    }
}
LoadError.args = {
    endpoint: endpoint,
}

export const PreSelectedClass = Template.bind({});
PreSelectedClass.parameters = {
    controls: { hideNoControlsWarning: true },
    msw: {
        handlers: {
            auth: rest.get(endpoint, (req, res, ctx) => {
                return res(ctx.status(403))
            }),
        }
    }
}
PreSelectedClass.args = {
    endpoint: '',
    previouslySelectedClass: { id: 80, name: '9A English (9ENGA)' },
}

export const LargeData = Template.bind({});
LargeData.parameters = {
    controls: { hideNoControlsWarning: true },
    msw: {
        handlers: [
            rest.get(endpoint, (req, res, ctx) => {
                return res(ctx.json(largeData)) //we grab an endpoint and then modify the data before it's returned
            }), 
        ]
    },
}
LargeData.args = {
    endpoint: endpoint,
}