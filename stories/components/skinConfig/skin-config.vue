<script setup lang="ts">
/**
 * To show the form
 */

const rootComputedStyle = getComputedStyle(document.documentElement);

const coreColors = [
    {
        label: "Background",
        hint: "The background colour",
        variable: "--color-background",
    },
    {
        label: "Foreground",
        hint: "The primary foreground colour",
        variable: "--color-foreground-primary",
    },
    {
        label: "Foreground / Secondary",
        hint: "The secondary foreground colour, ideally a subtle contrast to the primary",
        variable: "--color-foreground-secondary",
    },
    {
        label: "Text",
        hint: "The primary text colour",
        variable: "--color-text-primary",
    },
    {
        label: "Text / Secondary",
        hint: "The secondary text colour, ideally slightly lighter than the primary",
        variable: "--color-text-secondary",
    },
];

const formColors = [
    {
        label: "Primary",
        hint: "The primary colour for all form elements",
        variable: "--sbx-color-accent",
    },
    {
        label: "Focus",
        hint: "The colour used to highlight the form element is focused",
        variable: "--sbx-color-accent",
    },
];

const buttonColors = [
    {
        label: "Primary",
        hint: "The primary colour for all buttons",
        variable: "--color-buttons",
    },
    {
        label: "Hover",
        hint: "The hover colour for all buttons",
        variable: "--color-buttons-hover",
    },
    {
        label: "Primary Text",
        hint: "Text colour of a primary button",
        variable: "--color-buttons-text",
    },
    {
        label: "Focus",
        hint: "The colour used to highlight the button is focused",
        variable: "--color-focus-buttons",
    },
];

/**
 * Change the color of the element
 * @param event - The event that triggered the change
 */
function onColorChange(event: Event): void {
    const colorPicker = event.target as HTMLInputElement;
    const color = colorPicker.value;
    document.documentElement.style.setProperty(colorPicker.getAttribute("data-change") as string, color);
}

window.Vue.onMounted(() => {
    const colorPickers = document.querySelectorAll("input[type='color']");
    Array.from(colorPickers).forEach((colorPicker) => {
        (colorPicker as HTMLInputElement).value = rootComputedStyle.getPropertyValue(
            colorPicker.getAttribute("data-change") as string,
        );
        colorPicker.addEventListener("input", onColorChange);
        colorPicker.addEventListener("change", onColorChange);
    });

    document.querySelector("#islands-rounded")?.addEventListener("change", (event) => {
        const checkbox = event.target as HTMLInputElement;
        document.documentElement.style.setProperty("--roundness-islands", checkbox.checked ? "var(--spacing-m)" : "0");
    });

    document.querySelector("#islands-drop-shadow")?.addEventListener("change", (event) => {
        const checkbox = event.target as HTMLInputElement;
        document.documentElement.style.setProperty("--shadow-island-length", checkbox.checked ? "1px" : "0px");
        document.documentElement.style.setProperty("--shadow-island-blur", checkbox.checked ? "0.25rem" : "0px");
    });

    document.querySelector("#islands-drop-shadow-size")?.addEventListener("change", (event) => {
        const rangeSlider = event.target as HTMLInputElement;
        document.documentElement.style.setProperty("--shadow-island-length", `${rangeSlider.value}px`);
    });

    document.querySelector("#islands-borders")?.addEventListener("change", (event) => {
        const checkbox = event.target as HTMLInputElement;
        document.documentElement.style.setProperty("--size-island-border", checkbox.checked ? "3px" : "0px");
    });

    document.querySelector("#islands-border-size")?.addEventListener("change", (event) => {
        const rangeSlider = event.target as HTMLInputElement;
        document.documentElement.style.setProperty("--size-island-border", `${rangeSlider.value}px`);
    });

    document.querySelector("#rounded-form-elements")?.addEventListener("change", (event) => {
        const checkbox = event.target as HTMLInputElement;
        document.documentElement.style.setProperty(
            "--roundness-form-elements",
            checkbox.checked ? "var(--spacing-s)" : "0",
        );
    });

    document.querySelector("#rounded-buttons")?.addEventListener("change", (event) => {
        const checkbox = event.target as HTMLInputElement;
        document.documentElement.style.setProperty("--roundness-buttons", checkbox.checked ? "var(--spacing-s)" : "0");
    });
});
</script>

<template>
    <div class="l-flex-col l-gap-l l-pad-l">
        <div class="c-island">
            <div class="l-flex-col l-gap-l">
                <div class="c-form-element">
                    <h2 class="c-label c-label--large f-no-margins">Core Colours</h2>
                    <div class="c-hint">These are the main colours that are used throughout the entire system.</div>
                </div>
                <div class="l-flex-row l-gap-l">
                    <div v-for="color in coreColors" :key="color.variable" class="c-form-element">
                        <label :for="color.variable" class="c-label">{{ color.label }}</label>
                        <div class="c-hint">{{ color.hint }}</div>
                        <input
                            :id="color.variable"
                            type="color"
                            class="f-no-margins"
                            name="head"
                            :data-change="color.variable"
                        />
                    </div>
                </div>
                <div class="c-form-element l-pad-l" style="background: var(--color-foreground-secondary)">
                    <label class="c-label f-no-margins">Secondary Foreground</label>
                    <div class="c-hint">To review changes made to the Foreground / Secondary</div>
                </div>
            </div>
        </div>
        <div class="c-island">
            <div class="l-flex-col l-gap-l">
                <div class="c-form-element">
                    <h2 class="c-label c-label--large f-no-margins">Islands</h2>
                    <div class="c-hint">
                        To better group related content, most page content typically sits on 'islands'.
                    </div>
                </div>
                <div class="l-flex-row l-gap-l">
                    <div class="c-form-element">
                        <label class="c-label c-toggle" for="islands-rounded">
                            Rounded
                            <div class="c-hint">Add rounded styles to all Islands.</div>
                            <input id="islands-rounded" type="checkbox" class="c-toggle__input" />
                            <span class="c-toggle__display" hidden>
                                <sbx-icon name="approve" color="var(--color-foreground-primary)" />
                                <sbx-icon name="close" color="var(--sbx-color-accent)" />
                            </span>
                        </label>
                    </div>
                    <div class="c-form-element c-input-range">
                        <label class="c-label" for="islands-drop-shadow-size">Drop shadow size</label>
                        <div class="c-hint">Size of the drop shadow.</div>
                        <input
                            id="islands-drop-shadow-size"
                            class="c-input-range__input"
                            type="range"
                            min="0"
                            max="7"
                            step="1"
                            value="0"
                        />
                    </div>
                    <div class="c-form-element">
                        <label for="--color-island-blur" class="c-label">Drop shadow colour</label>
                        <div class="c-hint">Colour of the drop shadow</div>
                        <input
                            id="--color-island-blur"
                            type="color"
                            class="f-no-margins"
                            data-change="--color-island-blur"
                        />
                    </div>
                    <div class="c-form-element c-input-range">
                        <label class="c-label" for="islands-border-size">Border size</label>
                        <div class="c-hint">Size of the border.</div>
                        <input
                            id="islands-border-size"
                            class="c-input-range__input"
                            type="range"
                            min="0"
                            max="5"
                            step="1"
                            value="0"
                        />
                    </div>
                    <div class="c-form-element">
                        <label for="--color-island-border" class="c-label">Border colour</label>
                        <div class="c-hint">Colour of the border</div>
                        <input
                            id="--color-island-border"
                            type="color"
                            class="f-no-margins"
                            data-change="--color-island-border"
                        />
                    </div>
                </div>
            </div>
        </div>
        <div class="c-island">
            <div class="l-flex-col l-gap-l">
                <div class="c-form-element">
                    <h2 class="c-label c-label--large f-no-margins">Form elements</h2>
                    <div class="c-hint">To alter the appearance of all form elements</div>
                </div>
                <div class="l-flex-row l-gap-l">
                    <div class="c-form-element">
                        <label class="c-label c-toggle" for="rounded-form-elements">
                            Rounded
                            <div class="c-hint">Add rounded styles to all form elements.</div>
                            <input id="rounded-form-elements" type="checkbox" class="c-toggle__input" />
                            <span class="c-toggle__display" hidden>
                                <sbx-icon name="approve" color="var(--color-foreground-primary)" />
                                <sbx-icon name="close" color="var(--sbx-color-accent)" />
                            </span>
                        </label>
                    </div>
                    <div v-for="color in formColors" :key="color.variable" class="c-form-element">
                        <label :for="color.variable" class="c-label">{{ color.label }}</label>
                        <div class="c-hint">{{ color.hint }}</div>
                        <input :id="color.variable" type="color" class="f-no-margins" :data-change="color.variable" />
                    </div>
                </div>
                <hr />
                <div class="c-form-element">
                    <label class="c-label">Sample form elements to review changes</label>
                </div>
                <div class="c-form-element">
                    <label for="input1" class="c-label c-label--required">Title</label>
                    <input id="input1" class="c-input-text" type="text" />
                </div>
                <div class="c-form-element">
                    <fieldset class="c-fieldset">
                        <div class="c-fieldset__container">
                            <legend class="c-label">List of options</legend>
                            <div class="l-flex-row l-gap-s">
                                <div class="l-flex-row l-flex--align-center">
                                    <input id="rad7a" name="list" type="radio" class="c-input-radio" checked />
                                    <label for="rad7a" class="c-label c-label--small l-pad-s--horiz">Name</label>
                                </div>
                                <div class="l-flex-row l-flex--align-center">
                                    <input id="rad8a" name="list" type="radio" class="c-input-radio" />
                                    <label for="rad8a" class="c-label c-label--small l-pad-s--horiz">Group</label>
                                </div>
                                <div class="l-flex-row l-flex--align-center">
                                    <input id="rad9a" name="list" type="radio" class="c-input-radio" />
                                    <label for="rad9a" class="c-label c-label--small l-pad-s--horiz">Role</label>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </div>
                <div class="c-form-element">
                    <fieldset class="c-fieldset">
                        <div class="c-fieldset__container">
                            <legend class="c-label">Filter</legend>
                            <div class="c-hint">Please select all that apply.</div>
                            <div class="l-flex-row l-gap-s">
                                <div class="l-flex-row l-flex--align-center">
                                    <input id="cbx4a" type="checkbox" class="c-input-checkbox" />
                                    <label for="cbx4a" class="c-label c-label--small l-pad-s--horiz"
                                        >Waiting List</label
                                    >
                                </div>
                                <div class="l-flex-row l-flex--align-center">
                                    <input id="cbx5a" type="checkbox" class="c-input-checkbox" />
                                    <label for="cbx5a" class="c-label c-label--small l-pad-s--horiz">Group Admin</label>
                                </div>
                                <div class="l-flex-row l-flex--align-center">
                                    <input id="cbx6a" type="checkbox" class="c-input-checkbox" />
                                    <label for="cbx6a" class="c-label c-label--small l-pad-s--horiz"
                                        >Write Access</label
                                    >
                                </div>
                                <div class="l-flex-row l-flex--align-center">
                                    <input id="cbx7a" type="checkbox" class="c-input-checkbox" />
                                    <label for="cbx7a" class="c-label c-label--small l-pad-s--horiz">Read Access</label>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </div>
                <div class="c-form-element">
                    <label for="standard-select" class="c-label c-label--required">Please select</label>
                    <div class="c-select">
                        <select id="standard-select">
                            <option value="Option 1">Option 1</option>
                            <option value="Option 2">Option 2</option>
                            <option value="Option 3">Option 3</option>
                            <option value="Option 4">Option 4</option>
                            <option value="Option 5">Option 5</option>
                            <option value="Option length">Option that has too long of a value to fit</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="c-island">
            <div class="l-flex-col l-gap-l">
                <div class="c-form-element">
                    <h2 class="c-label c-label--large f-no-margins">Buttons</h2>
                    <div class="c-hint">To alter the appearance of all buttons</div>
                </div>
                <div class="l-flex-row l-gap-l">
                    <div class="c-form-element">
                        <label class="c-label c-toggle" for="rounded-buttons">
                            Rounded
                            <div class="c-hint">Add rounded styles to all form elements.</div>
                            <input id="rounded-buttons" type="checkbox" class="c-toggle__input" />
                            <span class="c-toggle__display" hidden>
                                <sbx-icon name="approve" color="var(--color-foreground-primary)" />
                                <sbx-icon name="close" color="var(--sbx-color-accent)" />
                            </span>
                        </label>
                    </div>
                    <div v-for="color in buttonColors" :key="color.variable" class="c-form-element">
                        <label :for="color.variable" class="c-label">{{ color.label }}</label>
                        <div class="c-hint">{{ color.hint }}</div>
                        <input :id="color.variable" type="color" class="f-no-margins" :data-change="color.variable" />
                    </div>
                </div>
                <hr />
                <div class="c-form-element">
                    <label class="c-label">Sample form elements to review changes</label>
                </div>
                <div class="l-flex-row l-gap-l">
                    <button type="button" class="c-button">
                        <sbx-icon name="advanced-list"></sbx-icon>
                        <span>Button</span>
                    </button>
                    <button type="button" class="c-button c-button--secondary">
                        <sbx-icon name="approve"></sbx-icon>
                        <span>Secondary Button</span>
                    </button>
                    <button type="button" class="c-button c-button--tertiary">
                        <span>Tertiary Button</span>
                        <sbx-icon name="more"></sbx-icon>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
