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
        label: "Form Elements Accent",
        hint: "The accent colour for all form elements",
        variable: "--color-form-elements",
    },
    {
        label: "Focus",
        hint: "The colour of the focus state for all interactive elements",
        variable: "--color-focus",
    },
];

/**
 * Change the color of the element
 * @param {Event} event - The event that triggered the change
 */
function onColorChange(event: Event) {
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

    document.querySelector("#islands-borders")?.addEventListener("change", (event) => {
        const checkbox = event.target as HTMLInputElement;
        document.documentElement.style.setProperty("--border-islands-size", checkbox.checked ? "3px" : "0px");
    });

    // document.querySelector("#rounded-form-elements")?.addEventListener("change", (event) => {
    //     const checkbox = event.target as HTMLInputElement;
    //     document.documentElement.style.setProperty("--roundness-inputs", checkbox.checked ? "var(--spacing-s)" : "0");
    // });
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
                        <input :id="color.variable" type="color" name="head" :data-change="color.variable" />
                    </div>
                </div>
            </div>
        </div>
        <div class="c-island">
            <div class="l-flex-col l-gap-l">
                <div class="c-form-element">
                    <h2 class="c-label c-label--large f-no-margins">Islands</h2>
                    <div class="c-hint">To better group related content, page content typically sits on 'islands'.</div>
                </div>
                <div class="c-form-element">
                    <label class="c-label c-toggle" for="islands-rounded">
                        Rounded corners?
                        <div class="c-hint">Add rounded borders to all Islands.</div>
                        <input id="islands-rounded" type="checkbox" class="c-toggle__input" />
                        <span class="c-toggle__display" hidden>
                            <sbx-icon name="approve" color="var(--color-foreground-primary)" />
                            <sbx-icon name="close" color="var(--color-form-elements)" />
                        </span>
                    </label>
                </div>
                <div class="c-form-element">
                    <label class="c-label c-toggle" for="islands-drop-shadow">
                        Drop shadow?
                        <div class="c-hint">Add a drop shadow to all Islands.</div>
                        <input id="islands-drop-shadow" type="checkbox" class="c-toggle__input" />
                        <span class="c-toggle__display" hidden>
                            <sbx-icon name="approve" color="var(--color-foreground-primary)" />
                            <sbx-icon name="close" color="var(--color-form-elements)" />
                        </span>
                    </label>
                </div>
                <div class="l-flex-row l-gap-l">
                    <div class="c-form-element">
                        <label class="c-label c-toggle" for="islands-borders">
                            Borders?
                            <div class="c-hint">Add a border to all Islands.</div>
                            <input id="islands-borders" type="checkbox" class="c-toggle__input" />
                            <span class="c-toggle__display" hidden>
                                <sbx-icon name="approve" color="var(--color-foreground-primary)" />
                                <sbx-icon name="close" color="var(--color-form-elements)" />
                            </span>
                        </label>
                    </div>
                    <div class="c-form-element">
                        <label for="--color-islands-border" class="c-label">Border colour</label>
                        <div class="c-hint">Colour of the border</div>
                        <input
                            id="--color-islands-border"
                            type="color"
                            name="head"
                            data-change="--color-islands-border"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- <div class="c-island">
            <h2>Styles</h2>
            <div class="l-flex-row l-gap-l">
                <label class="c-label c-toggle" for="rounded-form-elements">
                    Interactive elements
                    <div class="c-hint">Add rounded styles to all interactive elements.</div>
                    <input id="rounded-form-elements" type="checkbox" class="c-toggle__input" checked />
                    <span class="c-toggle__display" hidden>
                        <sbx-icon name="approve" color="var(--color-foreground-primary)" />
                        <sbx-icon name="close" color="var(--color-form-elements)" />
                    </span>
                </label>
            </div>
        </div>
        <div class="c-island">
            <h2>Colours</h2>
            <div class="l-flex-row l-gap-l">
                <div v-for="color in formColors" :key="color.variable" class="l-flex-col l-gap-s">
                    <label :for="color.variable" class="c-label">{{ color.label }}</label>
                    <div class="c-hint">{{ color.hint }}</div>
                    <input :id="color.variable" type="color" name="head" :data-change="color.variable" />
                </div>
            </div>
        </div>
        <div class="c-island">
            <div class="l-flex-col l-gap-l">
                <div class="l-flex-col l-gap-s">
                    <label for="input1" class="c-label c-label--required">Title</label>
                    <input id="input1" class="c-input-text" type="text" />
                </div>
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
                <fieldset class="c-fieldset">
                    <div class="c-fieldset__container">
                        <legend class="c-label">Filter</legend>
                        <div class="c-hint">Please select all that apply.</div>
                        <div class="l-flex-row l-gap-s">
                            <div class="l-flex-row l-flex--align-center">
                                <input id="cbx4a" type="checkbox" class="c-input-checkbox" />
                                <label for="cbx4a" class="c-label c-label--small l-pad-s--horiz">Waiting List</label>
                            </div>
                            <div class="l-flex-row l-flex--align-center">
                                <input id="cbx5a" type="checkbox" class="c-input-checkbox" />
                                <label for="cbx5a" class="c-label c-label--small l-pad-s--horiz">Group Admin</label>
                            </div>
                            <div class="l-flex-row l-flex--align-center">
                                <input id="cbx6a" type="checkbox" class="c-input-checkbox" />
                                <label for="cbx6a" class="c-label c-label--small l-pad-s--horiz">Write Access</label>
                            </div>
                            <div class="l-flex-row l-flex--align-center">
                                <input id="cbx7a" type="checkbox" class="c-input-checkbox" />
                                <label for="cbx7a" class="c-label c-label--small l-pad-s--horiz">Read Access</label>
                            </div>
                        </div>
                    </div>
                </fieldset>
                <div class="l-flex-col l-gap-s">
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
                <label class="c-label c-toggle" for="tgl3">
                    Enable Curriculum Code Tagging
                    <div class="c-hint">
                        If toggled, curriculum codes can be applied to Courses, Units, Badges, Lesson Plans, Tasks,
                        Quizzes, Due Work and Rubrics for the purpose of curriculum mapping.
                    </div>
                    <input id="tgl3" type="checkbox" class="c-toggle__input" checked />
                    <span class="c-toggle__display" hidden>
                        <sbx-icon name="approve" color="var(--color-foreground-primary)" />
                        <sbx-icon name="close" color="var(--color-form-elements)" />
                    </span>
                </label>
                <div class="l-flex-col l-gap-s">
                    <label class="c-label" for="start-date">Start date</label>
                    <div>
                        <input
                            id="start-date"
                            class="c-input-text c-input-text--with-button l-flex__item--1"
                            type="text"
                        />
                        <button class="c-button c-button--with-input l-width-100">
                            <sbx-icon name="calendar"></sbx-icon>
                            <span>Select date</span>
                        </button>
                    </div>
                </div>
                <div class="l-flex-row l-gap-s">
                    <button class="c-button c-button--secondary l-flex__item--1" type="button">Secondary Action</button>
                    <button class="c-button c-button--success l-flex__item--1" type="button">Primary Action</button>
                </div>
            </div>
        </div> -->
    </div>
</template>

<style scoped></style>
