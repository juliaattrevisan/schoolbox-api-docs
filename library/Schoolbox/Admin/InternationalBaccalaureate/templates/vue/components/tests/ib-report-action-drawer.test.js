import { mount } from "@vue/test-utils";
import IbReportActionDrawer from "../ib-report-action-drawer.vue";

function mountDrawer(props = {}) {
    return mount(IbReportActionDrawer, {
        props: {
            selectedCount: 1,
            isLoading: false,
            activeSection: "failed",
            ...props,
        },
        global: {
            stubs: {
                "bottom-drawer": {
                    template: "<div><slot /></div>",
                    props: ["showDrawer"],
                },
                "sbx-icon": true,
            },
        },
    });
}

test("emits registerViaApi when registering failed selections via API", async () => {
    const wrapper = mountDrawer();
    const registerButton = wrapper.findAll("button").find((button) => button.text().includes("Register via API"));

    expect(registerButton).toBeDefined();

    await registerButton.trigger("click");

    expect(wrapper.emitted("registerViaApi")).toEqual([[]]);
});
