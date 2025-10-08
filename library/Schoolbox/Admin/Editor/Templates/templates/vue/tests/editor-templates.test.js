import { mount } from "@vue/test-utils";
import EditorTemplates from "../editor-templates.vue";

// Mock fetch globally
global.fetch = jest.fn();

describe("EditorTemplates", () => {
    const defaultProps = {
        categories: [
            { id: 1, name: "badge-evidence" },
            { id: 2, name: "calendar-events" },
            { id: 3, name: "email" },
        ],
        templatesEndpoint: "",
        createTemplateEndpoint: "",
        updateTemplateEndpoint: "",
    };

    test("renders without crashing", () => {
        const wrapper = mount(EditorTemplates, {
            props: defaultProps,
        });
        expect(wrapper.exists()).toBe(true);
    });

    test("initializes with first category selected", () => {
        const wrapper = mount(EditorTemplates, {
            props: defaultProps,
        });
        expect(wrapper.vm.currentCategory.id).toBe(1);
        expect(wrapper.vm.currentCategory.name).toBe("badge-evidence");
    });

    test("does not load templates when endpoint is empty", async () => {
        const wrapper = mount(EditorTemplates, {
            props: defaultProps,
        });
        await wrapper.vm.loadCategoryTemplates();
        expect(fetch).not.toHaveBeenCalled();
    });
});
