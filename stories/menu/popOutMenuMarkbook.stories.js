import PopOutMenuMarkbook from "../../vue/components/menu/pop-out-menu-markbook.vue";
import { rest } from "msw";

export default {
    title: "Menu/PopOutMenuMarkbook",
    component: PopOutMenuMarkbook,
};

const endpoint = "/learning/assessment/timeline/48/user/65";

const DefaultTemplate = (args, { argTypes }) => ({
    components: { PopOutMenuMarkbook },
    setup() {
        return { args };
    },
    template: `
        <pop-out-menu-markbook v-bind="args" />
        `,
});

export const Default = DefaultTemplate.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
    msw: {
        handlers: [
            rest.get(endpoint, (req, res, ctx) => {
                return res(
                    ctx.json([
                        {
                            type: "feedback",
                            effector: {
                                id: 2,
                                name: "Jack Coles",
                            },
                            published: true,
                            date: {
                                datetime: "2022-12-22 19:56:21",
                                short: "Dec 22, 2022 7:56pm",
                                relative: "1 month ago",
                            },
                        },
                        {
                            type: "response",
                            effector: {
                                id: 65,
                                name: "Edwin Barnhams",
                            },
                            submitted: true,
                            submittedOnTime: true,
                            date: {
                                datetime: "2022-12-18 13:34:41",
                                short: "Dec 18, 2022 1:34pm",
                                relative: "1 month ago",
                            },
                        },
                    ])
                ); //we grab an endpoint and then modify the data before it's returned
            }),
        ],
    },
};

Default.play = async () => {
    //Dummy up a fake 'td' as the Vue component is expecting to receive a HTMLDivElement.
    const dummyTd = document.createElement("td");
    dummyTd.classList.add(["student-grade-cell", "is-selected"]);
    dummyTd.dataset.popOutMenu = "";
    dummyTd.dataset.endpoint = endpoint;
    (dummyTd.dataset.updateRoute = "/learning/assessments/48/65/responses"),
        (dummyTd.dataset.reviewRoute = "/learning/assessments/48/65/responses"),
        (dummyTd.dataset.publishRoute = "/learning/assessments/48/65"),
        (dummyTd.dataset.excludeRoute = "/learning/assessments/48/modify"),
        (dummyTd.dataset.historyRoute = "/learning/assessments/48/65"),
        // Dispatch an event to do the rest of the behind the scenes work.
        document.dispatchEvent(
            new CustomEvent("pop-out-menu-markbook__selected", {
                detail: {
                    target: dummyTd,
                    routes: {
                        dataEndpoint: dummyTd.dataset.endpoint,
                        updateRoute: dummyTd.dataset.updateRoute,
                        reviewRoute: dummyTd.dataset.reviewRoute,
                        publishRoute: dummyTd.dataset.publishRoute,
                        excludeRoute: dummyTd.dataset.excludeRoute,
                        historyRoute: dummyTd.dataset.historyRoute,
                    },
                },
            })
        );
};
