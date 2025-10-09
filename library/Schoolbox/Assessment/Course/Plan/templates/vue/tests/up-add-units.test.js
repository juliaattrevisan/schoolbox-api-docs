import { mount } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { ref, computed } from "vue";
import UpAddUnits from "../up-add-units.vue";
import { useAddUnitsStore } from "../store/unitPlannerAddUnitsStore";
import { useUnitPlannerStore } from "../store/unitPlannerStore";

// Mock the locale
jest.mock("@js/terminology/rosettaStone", () => ({
    unit: {
        translate: jest.fn((plural, capitalised) => {
            if (plural && capitalised) return "Units";
            if (plural && !capitalised) return "units";
            if (!plural && capitalised) return "Unit";
            return "unit";
        }),
    },
}));

// Mock the API utils
jest.mock("@js/util/sbx/apiUtils", () => ({
    apiRequest: jest.fn(),
}));

// Mock global properties
global.window = {
    ...global.window,
    Vue: {
        ref,
        computed,
        watch: jest.fn(),
    },
    sbProps: {
        toastActivate: jest.fn(() => ({
            timeout: jest.fn(),
        })),
    },
};

describe("UpAddUnits", () => {
    let wrapper;
    let addUnitsStore;
    let unitPlannerStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        addUnitsStore = useAddUnitsStore();
        unitPlannerStore = useUnitPlannerStore();

        // Setup default store state
        addUnitsStore.isOpen = true;
        unitPlannerStore.courseId = 123;

        wrapper = mount(UpAddUnits, {
            global: {
                stubs: {
                    "basic-modal": {
                        template: "<div><slot /></div>",
                        props: ["is-open"],
                    },
                    "request-error": {
                        template: '<div class="request-error">{{ furtherInfo }}</div>',
                        props: ["furtherInfo"],
                    },
                },
            },
        });
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    test("creation should not be possible when name input is empty", async () => {
        // Ensure the modal is visible
        expect(wrapper.find('[data-test="up-add-units-title"]').exists()).toBe(true);

        // Ensure the first input is empty
        const nameInput = wrapper.find('[data-test="up-add-units-name-input-0"]');
        expect(nameInput.exists()).toBe(true);
        expect(nameInput.element.value).toBe("");

        // Verify that the store initially has an empty string for the first unit
        expect(addUnitsStore.newUnits[0]).toBe("");

        // Submit the form (which triggers the onAddSelected function)
        const form = wrapper.find("form");
        await form.trigger("submit");

        // Wait for Vue to update
        await wrapper.vm.$nextTick();

        // Verify that validation errors are shown
        expect(addUnitsStore.validationErrors[0]).toBe(true);

        // Verify that the error label is displayed
        const errorLabel = wrapper.find(".error");
        expect(errorLabel.exists()).toBe(true);
        expect(errorLabel.text()).toContain("Please add a unit name");

        // Verify that the modal is still open (creation didn't proceed)
        expect(addUnitsStore.isOpen).toBe(true);
    });

    test("creation should proceed when name input has value", async () => {
        const unitName = "Test Unit";

        // Fill in the first input with a unit name
        const nameInput = wrapper.find('[data-test="up-add-units-name-input-0"]');
        await nameInput.setValue(unitName);

        // Verify that the store has the unit name
        expect(addUnitsStore.newUnits[0]).toBe(unitName);

        // Mock the API request to succeed
        const { apiRequest } = require("@js/util/sbx/apiUtils");
        apiRequest.mockResolvedValue({
            success: true,
            data: [{ id: 1, name: unitName }],
        });

        // Submit the form
        const form = wrapper.find("form");
        await form.trigger("submit");

        // Wait for the async operation to complete
        await new Promise((resolve) => setTimeout(resolve, 0));
        await wrapper.vm.$nextTick();

        // Verify that no error label is displayed (successful validation)
        const errorLabel = wrapper.find(".error");
        expect(errorLabel.exists()).toBe(false);
    });
});
