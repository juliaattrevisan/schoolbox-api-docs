import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    title: "Cards/SearchCards",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Assessments: Story = {
    render: () => ({
        template: `
        <div class="sb-card-list">
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-due-work"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h6 class="">Essay - Final Assessment</h6></a>
                    </div>
                </div>
            </div>
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-quiz"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h6 class="">Pre-unit Quiz</h6></a>
                    </div>
                </div>
            </div>
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-project"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h6 class="">Romeo and Juliet Project</h6></a>
                    </div>
                </div>
            </div>
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-task"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h6 class="">Post to Forum: Prologue</h6></a>
                    </div>
                </div>
            </div>
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-lesson-plan"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h6 class="">Romeo and Juliet Character Analysis</h6></a>
                    </div>
                </div>
            </div>
        </div>
        `,
    }),
};

export const Badge: Story = {
    render: () => ({
        template: `
            <div class="sb-card">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img--badge">
                        <div class="img__wrapper">
                            <img src='data:image/svg+xml;base64,//48AD8AeABtAGwAIAB2AGUAcgBzAGkAbwBuAD0AIgAxAC4AMAAiACAAZQBuAGMAbwBkAGkAbgBnAD0AIgBVAFQARgAtADEANgAiAD8APgANAAoAPAAhAEQATwBDAFQAWQBQAEUAIABzAHYAZwAgAFAAVQBCAEwASQBDACAAIgAtAC8ALwBXADMAQwAvAC8ARABUAEQAIABTAFYARwAgADEALgAwAC8ALwBFAE4AIgAgACIAaAB0AHQAcAA6AC8ALwB3AHcAdwAuAHcAMwAuAG8AcgBnAC8AVABSAC8AMgAwADAAMQAvAFIARQBDAC0AUwBWAEcALQAyADAAMAAxADAAOQAwADQALwBEAFQARAAvAHMAdgBnADEAMAAuAGQAdABkACIAPgANAAoAPAAhAC0ALQAgAEMAcgBlAGEAdABvAHIAOgAgAEMAbwByAGUAbABEAFIAQQBXACAAWAA2ACAALQAtAD4ADQAKADwAcwB2AGcAIAB4AG0AbABuAHMAPQAiAGgAdAB0AHAAOgAvAC8AdwB3AHcALgB3ADMALgBvAHIAZwAvADIAMAAwADAALwBzAHYAZwAiACAAeABtAGwAOgBzAHAAYQBjAGUAPQAiAHAAcgBlAHMAZQByAHYAZQAiACAAdwBpAGQAdABoAD0AIgAyADYAMABwAHgAIgAgAGgAZQBpAGcAaAB0AD0AIgAyADYAMABwAHgAIgAgAHYAZQByAHMAaQBvAG4APQAiADEALgAwACIAIABzAHQAeQBsAGUAPQAiAHMAaABhAHAAZQAtAHIAZQBuAGQAZQByAGkAbgBnADoAZwBlAG8AbQBlAHQAcgBpAGMAUAByAGUAYwBpAHMAaQBvAG4AOwAgAHQAZQB4AHQALQByAGUAbgBkAGUAcgBpAG4AZwA6AGcAZQBvAG0AZQB0AHIAaQBjAFAAcgBlAGMAaQBzAGkAbwBuADsAIABpAG0AYQBnAGUALQByAGUAbgBkAGUAcgBpAG4AZwA6AG8AcAB0AGkAbQBpAHoAZQBRAHUAYQBsAGkAdAB5ADsAIABmAGkAbABsAC0AcgB1AGwAZQA6AGUAdgBlAG4AbwBkAGQAOwAgAGMAbABpAHAALQByAHUAbABlADoAZQB2AGUAbgBvAGQAZAAiAA0ACgB2AGkAZQB3AEIAbwB4AD0AIgAwACAAMAAgADMANgA3ADEANAAgADMANgA3ADEANAAiAA0ACgAgAHgAbQBsAG4AcwA6AHgAbABpAG4AawA9ACIAaAB0AHQAcAA6AC8ALwB3AHcAdwAuAHcAMwAuAG8AcgBnAC8AMQA5ADkAOQAvAHgAbABpAG4AawAiAD4ADQAKACAAPABkAGUAZgBzAD4ADQAKACAAIAA8AHMAdAB5AGwAZQAgAHQAeQBwAGUAPQAiAHQAZQB4AHQALwBjAHMAcwAiAD4ADQAKACAAIAAgADwAIQBbAEMARABBAFQAQQBbAA0ACgAgACAAIAAgAC4AZgBpAGwAMQAgAHsAZgBpAGwAbAA6ACMAMABGAEEAQQBDADIAfQANAAoAIAAgACAAIAAuAGYAaQBsADAAIAB7AGYAaQBsAGwAOgAjADIANQBDADIARABCAH0ADQAKACAAIAAgACAALgBmAGkAbAA2ACAAewBmAGkAbABsADoAIwA0AEQANABEADQARAB9AA0ACgAgACAAIAAgAC4AZgBpAGwANAAgAHsAZgBpAGwAbAA6ACMANgA2ADYANgA2ADYAfQANAAoAIAAgACAAIAAuAGYAaQBsADIAIAB7AGYAaQBsAGwAOgAjAEYARgBEADYAMwAwAH0ADQAKACAAIAAgACAALgBmAGkAbAAzACAAewBmAGkAbABsADoAdwBoAGkAdABlAH0ADQAKACAAIAAgACAALgBmAGkAbAA1ACAAewBmAGkAbABsADoAIwBGAEYARAA2ADMAMAA7AGYAaQBsAGwALQByAHUAbABlADoAbgBvAG4AegBlAHIAbwB9AA0ACgAgACAAIABdAF0APgANAAoAIAAgADwALwBzAHQAeQBsAGUAPgANAAoAIAA8AC8AZABlAGYAcwA+AA0ACgAgADwAZwAgAGkAZAA9ACIATABhAHkAZQByAF8AeAAwADAAMgAwAF8AMQAiAD4ADQAKACAAIAA8AG0AZQB0AGEAZABhAHQAYQAgAGkAZAA9ACIAQwBvAHIAZQBsAEMAbwByAHAASQBEAF8AMABDAG8AcgBlAGwALQBMAGEAeQBlAHIAIgAvAD4ADQAKACAAIAA8AGMAaQByAGMAbABlACAAYwBsAGEAcwBzAD0AIgBmAGkAbAAwACIAIABjAHgAPQAiADEAOAAzADUANwAiACAAYwB5AD0AIgAxADgAMwA1ADcAIgAgAHIAPQAiADEAOAAzADUANwAiAC8APgANAAoAIAAgADwAcABhAHQAaAAgAGMAbABhAHMAcwA9ACIAZgBpAGwAMQAiACAAZAA9ACIATQAzADYANwAxADEAIAAxADgANQA4ADMAbAAtADkANAAyADIAIAAtADkANAAyADIAIAAtADEAOQA1ADQAMQAgADEAMgA5ADQANAAgADEAOAA0ADcAIAAxADgANAA3ACAALQAyADYAMgAgADIANgAyACAAMQAxADYAOAAgADEAMQA2ADgAIAAtADUAMwA3ACAANQAzADcAIAAyADUANAA2ACAAMgA1ADQANgAgAC0ANwAxADQAIAA5ADMAMwAgADcAMwAwADAAIAA3ADMAMAAwAGMAOQA3ADIAMAAsAC0AMwA4ADYAIAAxADcANAA5ADYALAAtADgAMwAyADkAIAAxADcANgAxADQALAAtADEAOAAxADEANQB6ACIALwA+AA0ACgAgACAAPABwAGEAdABoACAAYwBsAGEAcwBzAD0AIgBmAGkAbAAyACIAIABkAD0AIgBNADIANQA0ADUANQAgADMAMQAxADIAMgBsAC0ANAA3ADIAIAAtADYANQAxADAAYwAwACwAMAAgADQAOAAyADAALAAtADQANgAzADcAIAA0ADIANwA5ACwALQAxADAANQA4ADEAIAAtADUANAAxACwALQA1ADkANAA0ACAALQA2ADUAMwAwACwALQA5ADYAOAAxACAALQAxADIANgAwADkALAAtADgAMAA2ADEAIAAtADYAMAA3ADkALAAxADYAMgAxACAALQA3ADAAOAA2ACwANgA0ADkAMQAgAC0ANwAxADEANQAsADcAMgA5ADAAIAAtADIAOQAsADcAOQA5ACAANwAyADAALAAyADgANAAyACAAMQA4ADAALAA0ADIAMwA4ACAALQA1ADQAMAAsADEAMwA5ADYAIAAtADIANAA3ADYALAAzADUAOAAwACAALQAyADIAOQA2ACwANAAyADMAMwAgADEAOAAwACwANgA1ADMAIAAxADgANAA2ACwANwA2ADUAIAAxADgANAA2ACwANwA2ADUAIAAwACwAMAAgADEAOQAsADYAMwAxACAALQAyADAAMgAsADcANgA2ACAALQAyADIAMQAsADEAMwA0ACAALQA5ADYALAA2ADUAOQAgADIANgA4ACwAOQA1ADEAIAAzADYANAAsADIAOQAyACAAOQA3ADAALAA0ADkAMAAgADkANwAwACwANAA5ADAAIAAwACwAMAAgAC0ANgA5ADgALAAxADUAOAAgAC0ANgAwADgALAA2ADUAMwAgADkAMAAsADQAOQA2ACAAMwAzADgALAA3ADQAMwAgADgANQA2ACwANwA2ADUAIAA1ADEAOAAsADIAMwAgADYANQAzACwALQAyADIANAAgADcANAAzACwANAA2ACAAOQAwACwAMgA3ADAAIAAxADQANwAsADIAOQAyACAAMQA0ADcALAAyADkAMgAgADAALAAwACAALQA1ADEANwAsADEAMwA0ADAAIAAtADYANwAsADIAMwAzADAAIAA0ADUAMQAsADkAOQAxACAAMQAxADQAOAAsADkAOQAxACAAMQA5ADUAOQAsADcAOAA5ACAAOAAxADEALAAtADIAMAAzACAAMgAzADgANwAsAC0ANgA1ADMAIAAyADMAOAA3ACwALQA2ADUAMwBsADQAMwAxACAAMgAxADkANwAgADkAMwAwADQAIAAwAHoAIgAvAD4ADQAKACAAIAA8AGcAIABpAGQAPQAiAF8ANAAwADAANwA2ADQANwA2ADAAIgA+AA0ACgAgACAAIAA8AHAAYQB0AGgAIABjAGwAYQBzAHMAPQAiAGYAaQBsADMAIgAgAGQAPQAiAE0AMQA5ADEAOQA0ACAAOQAwADAANwBjADIAMgAxADIALAAwACAANAAwADAANgAsADEAOAAzADMAIAA0ADAAMAA2ACwANAAwADkANAAgADAALAAxADUAMwAwACAALQA4ADIAMQAsADIAOAA2ADQAIAAtADIAMAAzADgALAAzADUANgA2AGwAMAAgADEAOAA3ADcAYwAwACwAMgAyADQAIAAtADEANwA5ACwANAAwADcAIAAtADMAOQA4ACwANAAwADcAbAAtADIAOQAzADYAIAAwAGMALQAyADEAOQAsADAAIAAtADMAOQA4ACwALQAxADgAMwAgAC0AMwA5ADgALAAtADQAMAA3AGwAMAAgAC0AMQA3ADYANwBjAC0AMQAzADIAOAAsAC0ANgA2ADcAIAAtADIAMgA0ADEALAAtADIAMAA2ADIAIAAtADIAMgA0ADEALAAtADMANgA3ADYAIAAwACwALQAyADIANgAxACAAMQA3ADkAMwAsAC0ANAAwADkANAAgADQAMAAwADYALAAtADQAMAA5ADQAegAiAC8APgANAAoAIAAgACAAPABwAGEAdABoACAAYwBsAGEAcwBzAD0AIgBmAGkAbAA0ACIAIABkAD0AIgBNADEANwA5ADYAOQAgADEAOAA4ADkANwBsADIANAA0ADkAIAAwAGMAMQA3ADYALAAwACAAMwAyADEALAAxADEAMgAgADMAMgAxACwAMgA0ADkAbAAwACAAMABjADAALAAxADMANwAgAC0AMQA0ADQALAAyADQAOQAgAC0AMwAyADEALAAyADQAOQAgADEANwA2ACwAMAAgADMAMgAxACwAMQAxADIAIAAzADIAMQAsADIANAA5AGwAMAAgADAAYwAwACwAMQAzADcAIAAtADEANAA0ACwAMgA0ADkAIAAtADMAMgAxACwAMgA0ADkAIAAxADcANgAsADAAIAAzADIAMQAsADEAMQAyACAAMwAyADEALAAyADQAOQBsADAAIAAwAGMAMAAsADEAMwA3ACAALQAxADQANAAsADIANAA5ACAALQAzADIAMQAsADIANAA5AGwALQAyADQANAA5ACAAMABjAC0AMQA3ADYALAAwACAALQAzADIAMQAsAC0AMQAxADIAIAAtADMAMgAxACwALQAyADQAOQBsADAAIAAwAGMAMAAsAC0AMQAzADcAIAAxADQANAAsAC0AMgA0ADkAIAAzADIAMQAsAC0AMgA0ADkAIAAtADEANwA2ACwAMAAgAC0AMwAyADEALAAtADEAMQAyACAALQAzADIAMQAsAC0AMgA0ADkAbAAwACAAMABjADAALAAtADEAMwA3ACAAMQA0ADQALAAtADIANAA5ACAAMwAyADEALAAtADIANAA5ACAALQAxADcANgAsADAAIAAtADMAMgAxACwALQAxADEAMgAgAC0AMwAyADEALAAtADIANAA5AGwAMAAgADAAYwAwACwALQAxADMANwAgADEANAA0ACwALQAyADQAOQAgADMAMgAxACwALQAyADQAOQB6ACIALwA+AA0ACgAgACAAIAA8AHAAYQB0AGgAIABjAGwAYQBzAHMAPQAiAGYAaQBsADUAIgAgAGQAPQAiAE0AMQA3ADkANAA4ACAAMQAyADkAOQA5AGwANwAxADkAIAA1ADEAOAAwACAANQAyADcAIAAwACAANQAyADcAIAAwACAANwAxADkAIAAtADUAMQA4ADAAIAAyADYAMQAgADMANgAgAC0ANwAzADUAIAA1ADIAOQA0ACAALQAxADYAIAAxADEANAAgAC0AMQAxADUAIAAwACAALQA2ADQAMQAgADAAIAAtADYANAAxACAAMAAgAC0AMQAxADUAIAAwACAALQAxADYAIAAtADEAMQA0ACAALQA3ADMANQAgAC0ANQAyADkANAAgADIANgAxACAALQAzADYAegAiAC8APgANAAoAIAAgACAAPABwAGEAdABoACAAYwBsAGEAcwBzAD0AIgBmAGkAbAAyACIAIABkAD0AIgBNADEAOQA1ADcANAAgADEAMgAzADQAMABsAC0AOAA1ACAANQA4ADAAIAAyADEANwA2ACAALQAzADIANQAgAC0AMgA5ADQAOQAgADEAMgAzADEAIAAxADAAOAAgAC0ANwA5ADcAIAAtADEAOQAzADUAIAAzADYANwAgADIANgA4ADQAIAAtADEAMAA1ADUAegAiAC8APgANAAoAIAAgACAAPABwAGEAdABoACAAYwBsAGEAcwBzAD0AIgBmAGkAbAA2ACIAIABkAD0AIgBNADEAOQAwADAANAAgADIAMQA0ADQAMQBsADAAIAA3ADQAIAAtADIANAAzACAAMAAgADAAIAAtADcANAAgAC0AOQAzACAAMABjAC0AOQAzACwAMAAgAC0AMQA3ADMALAAtADUAOAAgAC0AMgAxADEALAAtADEANAAyAGwALQA3ACAAMABjAC0AMQAyADkALAAwACAALQAyADMAMwAsAC0AMQAxADMAIAAtADIAMwAzACwALQAyADUAMQBsADAAIAAwAGMAMAAsAC0AOAA4ACAANAAyACwALQAxADYANgAgADEAMAA2ACwALQAyADEAMABsAC0AMwA1ADgAIAAwAGMALQAxADIAOQAsADAAIAAtADIAMwAzACwALQAxADEAMwAgAC0AMgAzADMALAAtADIANQAxAGwAMAAgADAAYwAwACwALQAxADMAOAAgADEAMAA1ACwALQAyADUAMQAgADIAMwAzACwALQAyADUAMQBsADIANAA1ADcAIAAwAGMAMQAyADkALAAwACAAMgAzADMALAAxADEAMwAgADIAMwAzACwAMgA1ADEAbAAwACAAMABjADAALAAxADMAOAAgAC0AMQAwADUALAAyADUAMQAgAC0AMgAzADMALAAyADUAMQBsAC0AMwA1ADgAIAAwAGMANgA0ACwANAA1ACAAMQAwADcALAAxADIAMgAgADEAMAA3ACwAMgAxADAAbAAwACAAMABjADAALAAxADMAOAAgAC0AMQAwADUALAAyADUAMQAgAC0AMgAzADMALAAyADUAMQBsAC0ANwAgADAAYwAtADMAOAAsADgANAAgAC0AMQAxADgALAAxADQAMgAgAC0AMgAxADEALAAxADQAMgBsAC0AOQA4ACAAMAAgADAAIAA3ADQAIAAtADIANAAyACAAMAAgADAAIAAtADcANAAgAC0AMwA3ADQAIAAwAHoAIgAvAD4ADQAKACAAIAA8AC8AZwA+AA0ACgAgADwALwBnAD4ADQAKADwALwBzAHYAZwA+AA0ACgA='/>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h3 class="">Year 9 English</h3></a>
                        <span class="meta">Senior Courses > English</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const Course: Story = {
    render: () => ({
        template: `
            <div class="sb-card">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img">
                        <div class="img__wrapper">
                            <div class="icon icon-course"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h3 class="">Year 9 English</h3></a>
                        <span class="meta">Senior Courses > English</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const Event: Story = {
    render: () => ({
        template: `
            <div class="sb-card">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img">
                        <div class="img__wrapper">
                            <div class="icon icon-calendar"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h3 class="">Year 9 English</h3></a>
                        <span class="meta">Senior Courses > English</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const Files: Story = {
    render: () => ({
        template: `
            <div class="sb-card-list">
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__main">
                        <div class="sb-card__primary-img img img-s">
                            <div class="img__wrapper">
                                <img src="https://schoolbox-static.cloud/23.0.4-4/images/mime/application-audio.svg"/>
                            </div>
                        </div>
                        <div class="sb-card__main-content">
                            <a href=""><h6 class="">A_file_name.wav</h6></a>
                            <span class="meta">Last updated 3 weeks ago</span>
                        </div>
                    </div>
                </div>
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__main">
                        <div class="sb-card__primary-img img img-s">
                            <div class="img__wrapper">
                            <img src="https://schoolbox-static.cloud/23.0.4-4/images/mime/application-video.svg"/>
                            </div>
                        </div>
                        <div class="sb-card__main-content">
                            <a href=""><h6 class="">A_file_name.mp4</h6></a>
                            <span class="meta">Last updated 3 weeks ago</span>
                        </div>
                    </div>
                </div>
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__main">
                        <div class="sb-card__primary-img img img-s">
                            <div class="img__wrapper">
                                <img src="https://schoolbox-static.cloud/23.0.4-4/images/mime/application-blank.svg"/>
                            </div>
                        </div>
                        <div class="sb-card__main-content">
                            <a href=""><h6 class="">A_file_name.abc</h6></a>
                            <span class="meta">Last updated 3 weeks ago</span>
                        </div>
                    </div>
                </div>
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__main">
                        <div class="sb-card__primary-img img img-s">
                            <div class="img__wrapper">
                                <img src="https://schoolbox-static.cloud/23.0.4-4/images/mime/application-pdf.svg"/>
                            </div>
                        </div>
                        <div class="sb-card__main-content">
                            <a href=""><h6 class="">A_file_name.pdf</h6></a>
                            <span class="meta">Last updated 3 weeks ago</span>
                        </div>
                    </div>
                </div>
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__main">
                        <div class="sb-card__primary-img img img-s">
                            <div class="img__wrapper">
                                <img src="https://placedog.net/120/120"/>
                            </div>
                        </div>
                        <div class="sb-card__main-content">
                            <a href=""><h6 class="">A_file_name.png</h6></a>
                            <span class="meta">Last updated 3 weeks ago</span>
                        </div>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const Forum: Story = {
    render: () => ({
        template: `
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-forum"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h6 class="">What was the most impactful line in Romeo and Juliet?</h6></a>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const Link: Story = {
    render: () => ({
        template: `
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-link"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href="https://www.youtube.com/watch?v=_xc7tNbjnHM" target="_blank"><h6 class="">Click here to receive $300,000 in cold hard cash!</h6></a>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const News: Story = {
    render: () => ({
        template: `
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-news"></div>
                        </div>
                    </div>
                     <div class="sb-card__main-content">
                        <a href=""><h3 class="">Shakespeare in the Park</h3></a>
                        <span class="meta">By <a href="">Jack Coles</a> — 2 days ago in </span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const Unit: Story = {
    render: () => ({
        template: `
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-unit"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h6 class="">Romeo and Juliet</h6></a>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const User: Story = {
    render: () => ({
        template: `
            <div class="sb-card">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img">
                        <div class="img__wrapper">
                            <img src="https://placedog.net/64/64" alt="Placedog 64 by 64 image" />
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h3 class="">Jana Reed</h3></a>
                        <span class="meta">Year 9 Senior Student</span>
                    </div>
                </div>
            </div>
        `,
    }),
};
