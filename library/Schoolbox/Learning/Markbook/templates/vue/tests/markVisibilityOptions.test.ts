import { getMarkVisibilityOptions } from "../markVisibilityOptions";

/**
 * Reduce options to the state relevant to these tests.
 * @param availableVisibilities Visibility values allowed by LMS policy.
 * @param currentVisibility The assessment's current saved visibility.
 * @returns Value and disabled states for the resulting options.
 */
function optionStates(
    availableVisibilities: string[],
    currentVisibility: string,
): { value: string; disabled: boolean }[] {
    return getMarkVisibilityOptions(availableVisibilities, currentVisibility).map(({ value, disabled }) => ({
        value,
        disabled: Boolean(disabled),
    }));
}

test("only policy-available visibility options are selectable", () => {
    expect(optionStates(["staffOnly"], "staffOnly")).toEqual([{ value: "staffOnly", disabled: false }]);
});

test("current unavailable visibility remains visible as disabled", () => {
    expect(optionStates(["staffOnly"], "staffAndStudent")).toEqual([
        { value: "staffOnly", disabled: false },
        { value: "staffAndStudent", disabled: true },
    ]);
});

test("current available visibility is selectable", () => {
    expect(optionStates(["staffOnly", "staffAndStudent"], "staffAndStudent")).toEqual([
        { value: "staffOnly", disabled: false },
        { value: "staffAndStudent", disabled: false },
    ]);
});
