
import ClassSelector from '../library/Schoolbox/Assessment/Course/templates/vue/class-selector';
import { rest } from 'msw';
import { data } from './dataClassSelector';

export default {
    title: 'IN PROGRESS/ClassSelector',
    component: ClassSelector,
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
            return res(
                ctx.json([
                        ...data,
                    ]),
                );
            }),
        ]
    },
}
Default.args = {
    endpoint: 'https://swapi.dev/api/films/',
}