import { mount } from "@vue/test-utils";
import WcActionsDropdown from "../sbx-pop-out.ce.vue";

beforeEach(() => {
    document.body.innerHTML = `
        <div id="container">
            <div id="app"></div>
            <div id="my-content" data-dropdown-content style="display: none;">Content</div>
        </div>
    `;
});

test("when clicked content is visible", async () => {
    const wrapper = mount(WcActionsDropdown, {
        attachTo: document.getElementById("app"),
        props: {
            contentId: "my-content",
        },
    });
    wrapper.get(`[data-test="sbx-pop-out-my-content-button"]`).trigger("click");
    const content = document.querySelector("#my-content");
    expect(content.style.display).toBe("block");
});

test("when clicked again content is hidden", async () => {
    const wrapper = mount(WcActionsDropdown, {
        attachTo: document.getElementById("app"),
        props: {
            contentId: "my-content",
        },
    });
    wrapper.get(`[data-test="sbx-pop-out-my-content-button"]`).trigger("click");
    wrapper.get(`[data-test="sbx-pop-out-my-content-button"]`).trigger("click");
    const content = document.querySelector("#my-content");
    expect(content.style.display).toBe("none");
});

test("button has text when provided", async () => {
    const wrapper = mount(WcActionsDropdown, {
        attachTo: document.getElementById("app"),
        props: {
            contentId: "my-content",
            buttonText: "Open My Content",
        },
    });
    const button = wrapper.get(`[data-test="sbx-pop-out-my-content-button"]`);
    expect(button.html()).toContain("Open My Content");
});
