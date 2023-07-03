import SearchPage from "../../library/Schoolbox/Search/templates/vue/search-page.vue";

export default {
    component: SearchPage,
    decorators: [
        () => ({
            template: `
                <div id="container">
                    <div id="content">
                        <story />
                    </div>
                </div>
            `,
        }),
    ],
    title: "Temp/SearchPage",
};

const Template = (args, { argTypes }) => ({
    components: { SearchPage },
    setup() {
        return { args };
    },
    template: `
        <search-page v-bind="args" />
        `,
});

export const Default = Template.bind({});
