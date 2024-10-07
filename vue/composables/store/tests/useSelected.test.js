import { useSelected } from "../useSelected";

test("single item is added to selected", () => {
    const { selected, add } = useSelected();
    add({ id: 1 });
    expect(selected.value).toHaveLength(1);
});

test("multiple items are in selected when added", () => {
    const { selected, add } = useSelected();
    add([{ id: 1 }, { id: 2 }, { id: 3 }]);
    expect(selected.value).toHaveLength(3);
});

test("single item is removed from selected", () => {
    const { selected, add, remove } = useSelected();
    add([{ id: 1 }, { id: 2 }, { id: 3 }]);
    remove({ id: 2 });
    expect(selected.value).toHaveLength(2);
});

test("multiple item is removed from selected", () => {
    const { selected, add, remove } = useSelected();
    add([{ id: 1 }, { id: 2 }, { id: 3 }]);
    remove([{ id: 1 }, { id: 2 }]);
    expect(selected.value).toHaveLength(1);
});

test("completely update selected", () => {
    const { selected, add, update } = useSelected();
    add([{ id: 1 }, { id: 2 }, { id: 3 }]);
    update([{ id: 4 }, { id: 5 }]);
    expect(selected.value).toEqual([{ id: 4 }, { id: 5 }]);
});

test("empty selected", () => {
    const { selected, add, empty } = useSelected();
    add([{ id: 1 }, { id: 2 }, { id: 3 }]);
    empty();
    expect(selected.value).toHaveLength(0);
});

test("isEmpty returns true when selected is empty", () => {
    const { isEmpty } = useSelected();
    expect(isEmpty.value).toBe(true);
});

test("isEmpty returns false when selected is not empty", () => {
    const { add, isEmpty } = useSelected();
    add([{ id: 1 }]);
    expect(isEmpty.value).toBe(false);
});

test("hasItems returns true when selected has items", () => {
    const { add, hasItems } = useSelected();
    add([{ id: 1 }]);
    expect(hasItems.value).toBe(true);
});

test("hasItems returns false when selected has no items", () => {
    const { hasItems } = useSelected();
    expect(hasItems.value).toBe(false);
});
