import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/ui/forms/_input-toggle.scss`
 *
 * **Usage notes**:
 * - Unlike previously, there's no option to vary what's displayed in the toggle
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        () => ({
            template: `
                <div class="c-island">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Draft/Form/Input Toggle",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Default: Story = {
    render: () => ({
        template: `
<label class="c-label c-toggle" for="tgl1">
    Enable Curriculum Code Tagging
    <input id="tgl1" type="checkbox" class="c-toggle__input" checked />
    <span class="c-toggle__display" hidden>
        <sbx-icon name="approve" color="var(--color-foreground-primary)" />
        <sbx-icon name="close" color="var(--sbx-color-accent)" />
    </span>
</label>
            `,
    }),
};

export const Disabled: Story = {
    render: () => ({
        template: `
<label class="c-label c-toggle" for="tgl2">
    Enable Curriculum Code Tagging
    <input id="tgl2" type="checkbox" class="c-toggle__input" checked disabled />
    <span class="c-toggle__display" hidden>
        <sbx-icon name="approve" color="var(--color-foreground-primary)" />
        <sbx-icon name="close" color="var(--sbx-color-accent)" />
    </span>
</label>
            `,
    }),
};

export const WithHint: Story = {
    render: () => ({
        template: `
<label class="c-label c-toggle" for="tgl3">
    Enable Curriculum Code Tagging
    <div class="c-hint">If toggled, curriculum codes can be applied to Courses, Units, Badges, Lesson Plans, Tasks, Quizzes, Due Work and Rubrics for the purpose of curriculum mapping.</div>
    <input id="tgl3" type="checkbox" class="c-toggle__input" checked />
    <span class="c-toggle__display" hidden>
        <sbx-icon name="approve" color="var(--color-foreground-primary)" />
        <sbx-icon name="close" color="var(--sbx-color-accent)" />
    </span>
</label>
            `,
    }),
};

export const Inline: Story = {
    render: () => ({
        template: `
<label class="c-label c-toggle c-toggle--inline" for="tgl4">
    <input id="tgl4" type="checkbox" class="c-toggle__input" checked />
    <span class="c-toggle__display" hidden>
        <sbx-icon name="approve" color="var(--color-foreground-primary)" />
        <sbx-icon name="close" color="var(--sbx-color-accent)" />
    </span>
    Enable Curriculum Code Tagging
</label>
            `,
    }),
};

export const RTL: Story = {
    render: () => ({
        template: `
<label class="c-label c-toggle" for="tgl5" dir="rtl">
    Enable Curriculum Code Tagging
    <input id="tgl5" type="checkbox" class="c-toggle__input" checked />
    <span class="c-toggle__display" hidden>
        <sbx-icon name="approve" color="var(--color-foreground-primary)" />
        <sbx-icon name="close" color="var(--sbx-color-accent)" />
    </span>
</label>
            `,
    }),
};
