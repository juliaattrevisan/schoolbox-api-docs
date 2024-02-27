import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * Sample form displaying all currently available form elements
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    title: "Draft/Form/Sample Page",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Default: Story = {
    render: () => ({
        template: `
            <div style="padding: 1rem;">
                <div class="c-island">
                    <div class="l-flex-col l-gap-l">
                        <div class="l-flex-col l-gap-s">
                            <label for="input1" class="c-label c-label--required">Title</label>
                            <input id="input1" class="c-input-text" type="text" />
                        </div>
                        <fieldset class="c-fieldset">
                            <div class="c-fieldset__container">
                                <legend class="c-label">List of options</legend>
                                <div class="l-flex-row l-gap-m">
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
                                <div class="l-flex-row l-gap-m">
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
                        <label class="c-label c-toggle" for="tgl3">
                            Enable Curriculum Code Tagging
                            <div class="c-hint">If toggled, curriculum codes can be applied to Courses, Units, Badges, Lesson Plans, Tasks, Quizzes, Due Work and Rubrics for the purpose of curriculum mapping.</div>
                            <input id="tgl3" type="checkbox" class="c-toggle__input" checked />
                            <span class="c-toggle__display" hidden>
                                <sbx-icon name="approve" color="var(--color-foreground-primary)" />
                                <sbx-icon name="close" color="var(--color-text-secondary)" />
                            </span>
                        </label>
                        <div class="l-flex-col l-gap-s">
                            <label class="c-label" for="start-date">Start date</label>
                            <div>
                                <input id="start-date" class="c-input-text c-input-text--with-button l-flex__item--1" type="text" />
                                <button class="c-button c-button--with-input l-width-100">
                                    <sbx-icon name="calendar"></sbx-icon>
                                    <span>Select date</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            `,
    }),
};
