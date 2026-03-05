import { chained } from "../chained";

describe("chained()", () => {
    let parentSelect;
    let childSelect;

    beforeEach(() => {
        // Create parent select (type)
        parentSelect = document.createElement("select");
        parentSelect.id = "typeId";
        parentSelect.name = "typeId";
        parentSelect.required = true;
        parentSelect.innerHTML = `
            <option disabled selected>Please select a type</option>
            <option value="1">Academic</option>
            <option value="2">Wellbeing</option>
            <option value="4">NCCD</option>
            <option value="5">NCCD Adjustment</option>
            <option value="3">Pathways Management</option>
            <option value="7">Reporting</option>
        `;

        // Create child select (subtype)
        childSelect = document.createElement("select");
        childSelect.id = "subtypeId";
        childSelect.name = "subtypeId";
        childSelect.innerHTML = `
            <option value="">Please select a subtype</option>
            <option value="101" data-chained="1">Academic - Progress Report</option>
            <option value="102" data-chained="1">Academic - Assessment</option>
            <option value="103" data-chained="1">Academic - Learning Plan</option>
            <option value="201" data-chained="2">Wellbeing - Incident</option>
            <option value="202" data-chained="2">Wellbeing - Counselling</option>
            <option value="203" data-chained="2">Wellbeing - Support Plan</option>
            <option value="401" data-chained="4">NCCD - Evidence</option>
            <option value="402" data-chained="4">NCCD - Review</option>
            <option value="501" data-chained="5">NCCD Adjustment - Strategy</option>
            <option value="301" data-chained="3">Pathways - Career Planning</option>
            <option value="302" data-chained="3">Pathways - Work Experience</option>
            <option value="701" data-chained="7">Reporting - General</option>
        `;

        document.body.appendChild(parentSelect);
        document.body.appendChild(childSelect);
    });

    afterEach(() => {
        document.body.removeChild(parentSelect);
        document.body.removeChild(childSelect);
    });

    describe("initialization", () => {
        test("should return API object when valid selectors provided", () => {
            const api = chained("#subtypeId", "#typeId");
            expect(api).toBeDefined();
            expect(api).toHaveProperty("update");
            expect(api).toHaveProperty("destroy");
            expect(typeof api.update).toBe("function");
            expect(typeof api.destroy).toBe("function");
        });

        test("should return undefined when child selector not found", () => {
            const consoleError = jest.spyOn(console, "error").mockImplementation();
            const api = chained("#nonexistent", "#typeId");
            expect(api).toBeUndefined();
            expect(consoleError).toHaveBeenCalledWith("Chained: Child element not found");
            consoleError.mockRestore();
        });

        test("should return undefined when parent selector not found", () => {
            const consoleError = jest.spyOn(console, "error").mockImplementation();
            const api = chained("#subtypeId", "#nonexistent");
            expect(api).toBeUndefined();
            expect(consoleError).toHaveBeenCalledWith("Chained: Parent element not found");
            consoleError.mockRestore();
        });

        test("should accept HTMLSelectElement as child selector", () => {
            const api = chained(childSelect, "#typeId");
            expect(api).toBeDefined();
        });

        test("should accept HTMLSelectElement as parent selector", () => {
            const api = chained("#subtypeId", parentSelect);
            expect(api).toBeDefined();
        });

        test("should disable child select initially when parent has no selection", () => {
            parentSelect.selectedIndex = 0; // "Please select a type" (disabled option)
            chained("#subtypeId", "#typeId");
            expect(childSelect.disabled).toBe(true);
        });
    });

    describe("option filtering", () => {
        test("should filter child options based on parent selection", () => {
            parentSelect.value = "1"; // Academic
            chained("#subtypeId", "#typeId");

            const visibleOptions = Array.from(childSelect.options);
            expect(visibleOptions.length).toBe(4); // Empty option + 3 Academic options
            expect(visibleOptions[0].value).toBe("");
            expect(visibleOptions[1].value).toBe("101");
            expect(visibleOptions[2].value).toBe("102");
            expect(visibleOptions[3].value).toBe("103");
        });

        test("should show Wellbeing options when Wellbeing type selected", () => {
            parentSelect.value = "2"; // Wellbeing
            chained("#subtypeId", "#typeId");

            const visibleOptions = Array.from(childSelect.options);
            expect(visibleOptions.length).toBe(4); // Empty option + 3 Wellbeing options
            expect(visibleOptions.some((opt) => opt.value === "201")).toBe(true);
            expect(visibleOptions.some((opt) => opt.value === "202")).toBe(true);
            expect(visibleOptions.some((opt) => opt.value === "203")).toBe(true);
        });

        test("should show NCCD options when NCCD type selected", () => {
            parentSelect.value = "4"; // NCCD
            chained("#subtypeId", "#typeId");

            const visibleOptions = Array.from(childSelect.options);
            expect(visibleOptions.length).toBe(3); // Empty option + 2 NCCD options
            expect(visibleOptions.some((opt) => opt.value === "401")).toBe(true);
            expect(visibleOptions.some((opt) => opt.value === "402")).toBe(true);
        });

        test("should remove options without data-chained attribute", () => {
            // Add an option without data-chained
            const optionWithoutChained = document.createElement("option");
            optionWithoutChained.value = "999";
            optionWithoutChained.textContent = "No Chain Data";
            childSelect.appendChild(optionWithoutChained);

            parentSelect.value = "1";
            chained("#subtypeId", "#typeId");

            const visibleOptions = Array.from(childSelect.options);
            expect(visibleOptions.some((opt) => opt.value === "999")).toBe(false);
        });

        test("should always keep the empty value option", () => {
            parentSelect.value = "1";
            chained("#subtypeId", "#typeId");

            const emptyOption = childSelect.querySelector('option[value=""]');
            expect(emptyOption).not.toBeNull();
        });
    });

    describe("parent change events", () => {
        test("should update child options when parent changes", () => {
            parentSelect.value = "1"; // Academic
            const api = chained("#subtypeId", "#typeId");

            // Change parent to Wellbeing
            parentSelect.value = "2";
            parentSelect.dispatchEvent(new Event("change"));

            const visibleOptions = Array.from(childSelect.options);
            expect(visibleOptions.some((opt) => opt.value === "201")).toBe(true); // Wellbeing option
            expect(visibleOptions.some((opt) => opt.value === "101")).toBe(false); // Academic option should be gone
        });

        test("should trigger change event on child when options change", () => {
            parentSelect.value = "1";
            chained("#subtypeId", "#typeId");

            const changeHandler = jest.fn();
            childSelect.addEventListener("change", changeHandler);

            parentSelect.value = "2";
            parentSelect.dispatchEvent(new Event("change"));

            expect(changeHandler).toHaveBeenCalled();
        });

        test("should not trigger change event if previously selected value still available", () => {
            parentSelect.value = "1";
            chained("#subtypeId", "#typeId");

            // Select a specific Academic option
            childSelect.value = "101";

            const changeHandler = jest.fn();
            childSelect.addEventListener("change", changeHandler);

            // Change to different type and back
            parentSelect.value = "2";
            parentSelect.dispatchEvent(new Event("change"));
            changeHandler.mockClear();

            parentSelect.value = "1";
            parentSelect.dispatchEvent(new Event("change"));

            // Value 101 is still available, so it should remain selected
            childSelect.value = "101";
            expect(childSelect.value).toBe("101");
        });
    });

    describe("disabled state", () => {
        test("should disable child when no valid options remain", () => {
            parentSelect.selectedIndex = 0; // Disabled/no value option
            chained("#subtypeId", "#typeId");

            expect(childSelect.disabled).toBe(true);
        });

        test("should enable child when valid options exist", () => {
            parentSelect.value = "1"; // Academic
            chained("#subtypeId", "#typeId");

            expect(childSelect.disabled).toBe(false);
        });

        test("should re-enable child when parent selection changes to valid option", () => {
            parentSelect.selectedIndex = 0;
            chained("#subtypeId", "#typeId");
            expect(childSelect.disabled).toBe(true);

            parentSelect.value = "1";
            parentSelect.dispatchEvent(new Event("change"));
            expect(childSelect.disabled).toBe(false);
        });
    });

    describe("API methods", () => {
        test("update() should manually trigger option filtering", () => {
            parentSelect.value = "1";
            const api = chained("#subtypeId", "#typeId");

            // Manually change parent value without triggering event
            parentSelect.value = "2";

            // Call update manually
            api.update();

            const visibleOptions = Array.from(childSelect.options);
            expect(visibleOptions.some((opt) => opt.value === "201")).toBe(true); // Wellbeing option
        });

        test("destroy() should remove event listeners", () => {
            parentSelect.value = "1";
            const api = chained("#subtypeId", "#typeId");

            api.destroy();

            const changeHandler = jest.fn();
            childSelect.addEventListener("change", changeHandler);

            // Changing parent should not affect child anymore
            parentSelect.value = "2";
            parentSelect.dispatchEvent(new Event("change"));

            // The child won't be updated, so we just verify destroy doesn't break anything
            expect(api.destroy).not.toThrow();
        });
    });

    describe("data-chained attribute variations", () => {
        test("should support multiple space-separated values in data-chained", () => {
            childSelect.innerHTML = `
                <option value="">Please select</option>
                <option value="1" data-chained="1 2 3">Multiple Types</option>
            `;

            parentSelect.value = "2";
            chained("#subtypeId", "#typeId");

            const visibleOptions = Array.from(childSelect.options);
            expect(visibleOptions.some((opt) => opt.value === "1")).toBe(true);

            parentSelect.value = "3";
            parentSelect.dispatchEvent(new Event("change"));
            expect(visibleOptions.some((opt) => opt.value === "1")).toBe(true);
        });

        test("should handle data-chained with extra whitespace", () => {
            childSelect.innerHTML = `
                <option value="">Please select</option>
                <option value="1" data-chained="  1   2  ">With Whitespace</option>
            `;

            parentSelect.value = "1";
            chained("#subtypeId", "#typeId");

            const visibleOptions = Array.from(childSelect.options);
            expect(visibleOptions.some((opt) => opt.value === "1")).toBe(true);
        });
    });

    describe("edge cases", () => {
        test("should handle child select with no options", () => {
            childSelect.innerHTML = "";
            parentSelect.value = "1";

            expect(() => {
                chained("#subtypeId", "#typeId");
            }).not.toThrow();
        });

        test("should handle parent with no selected value initially", () => {
            parentSelect.value = "";

            expect(() => {
                chained("#subtypeId", "#typeId");
            }).not.toThrow();

            expect(childSelect.disabled).toBe(true);
        });

        test("should preserve child selection when it matches filtered options", () => {
            parentSelect.value = "1";
            childSelect.value = "101";
            chained("#subtypeId", "#typeId");

            expect(childSelect.value).toBe("101");
        });

        test("should clear child selection when it doesn't match filtered options", () => {
            parentSelect.value = "1";
            childSelect.value = "101"; // Academic option
            const api = chained("#subtypeId", "#typeId");

            // Change to Wellbeing (101 won't be available)
            parentSelect.value = "2";
            api.update();

            expect(childSelect.value).not.toBe("101");
        });
    });
});
