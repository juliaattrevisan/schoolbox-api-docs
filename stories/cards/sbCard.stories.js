import StorybookSlot from "../../vue/components/storybook-slot";

export default {
    title: "Cards/SbCard",
    component: StorybookSlot,
};

const DefaultTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img">
                    <img src="https://placedog.net/64/64" alt="Placedog 64 by 64 image" />
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                    <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                </div>
                <div class="sb-card__top-actions">
                    <nav class="sb-nav-actions">
                        <a class="icon-modify no-text" href="" title="Modify"></a>
                        <a class="icon-modify no-text" href="" title="Modify"></a>
                        <a class="icon-more no-text" href="" title="Actions"></a>
                    </nav>
                </div>
            </div>
            <div class="sb-card__content">
                <article>
                    <p>Come and see why Schoolbox is more than a school.&nbsp;&nbsp;You can find out more about the broad range of learning experiences offered - from academia,
                        internationalism and earth studies, to sensational music, theatre and sport.&nbsp; Talk to students, staff&nbsp;and parents to learn more at Open Day.&nbsp;&nbsp;<br><br>Caulfield
                        Grammar's three metropolitan campuses -&nbsp;Schoolbox&nbsp;Campus, Mars Campus and Venus Campus will conduct Open Days on:</p>
                    <blockquote>
                        <ul>
                            <li><strong>Saturday 17 August, 11am - 3pm</strong></li>
                        </ul>
                    </blockquote>
                    <p>Of course tours are available at any time. Please&nbsp;<a href="http://www.schoolbox.com.au" target="_self">contact us</a>&nbsp;to arrange an appointment.</p>
                </article>
            </div>
            <div class="sb-card__actions">
                <a>READ MORE</a>
                <a>ANOTHER ACTION</a>
            </div>
        </div>
    `,
});

const SubItemTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card sb-card--subitem">
            <div class="sb-card__top">
                <div class="sb-card__primary-img">
                    <img src="https://placedog.net/40/40" alt="Placedog 64 by 64 image" />
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h6 class="">How cool is this dog?</h6></a>
                    <span class="meta">Last updated 3 weeks ago</span>
                </div>
            </div>
        </div>
    `,
});

const CardListTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card-list">
            <div class="sb-card">
                <div class="sb-card__top">
                    <div class="sb-card__primary-img">
                        <div class="icon icon-folder"></div>
                    </div>
                    <div class="sb-card__top-content">
                        <a href=""><h3 class="">Introductory Biology (BIO11)</h3></a>
                        <span class="meta">Resources > Science > Year 11</span>
                    </div>
                </div>
            </div>

            <div class="sb-card-list">
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__top">
                        <div class="sb-card__primary-img">
                            <div class="icon icon-link"></div>
                        </div>
                        <div class="sb-card__top-content">
                            <a href=""><h6 class="">Ted Talk - The importance of Biology in the modern world</h6></a>
                        </div>
                    </div>
                </div>
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__top">
                        <div class="sb-card__primary-img">
                            <img class="mime" src="https://vagrant.dev.schoolbox.cloud/static/images/mime/application-pdf.svg" alt="PDF Document" />
                        </div>
                        <div class="sb-card__top-content">
                            <a href=""><h6 class="">Biology for Dummies</h6></a>
                        </div>
                    </div>
                </div>
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__top">
                        <div class="sb-card__primary-img">
                            <img src="https://placedog.net/40/40" alt="Placedog 40 by 40 image" />
                        </div>
                        <div class="sb-card__top-content">
                            <a href=""><h6 class="">How cool is this dog?</h6></a>
                            <span class="meta">Last updated 3 weeks ago</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sb-card">
                <div class="sb-card__top">
                    <div class="sb-card__primary-img">
                        <div class="icon icon-folder"></div>
                    </div>
                    <div class="sb-card__top-content">
                        <a href=""><h3 class="">Advanced Biology (BIO12)</h3></a>
                        <span class="meta">Resources > Science > Year 12</span>
                    </div>
                </div>
            </div>

            <div class="sb-card-list">
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__top">
                        <div class="sb-card__primary-img">
                            <div class="icon icon-link"></div>
                        </div>
                        <div class="sb-card__top-content">
                            <a href=""><h6 class="">Ted Talk - The importance of Biology in the modern world</h6></a>
                        </div>
                    </div>
                </div>
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__top">
                        <div class="sb-card__primary-img">
                            <img class="mime" src="https://vagrant.dev.schoolbox.cloud/static/images/mime/application-pdf.svg" alt="PDF Document" />
                        </div>
                        <div class="sb-card__top-content">
                            <a href=""><h6 class="">Biology for Dummies</h6></a>
                        </div>
                    </div>
                </div>
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__top">
                        <div class="sb-card__primary-img">
                            <img src="https://placedog.net/40/40" alt="Placedog 40 by 40 image" />
                        </div>
                        <div class="sb-card__top-content">
                            <a href=""><h6 class="">How cool is this dog?</h6></a>
                            <span class="meta">Last updated 3 weeks ago</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `,
});

export const Default = DefaultTemplate.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const SubItem = SubItemTemplate.bind({});
SubItem.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const CardList = CardListTemplate.bind({});
CardList.parameters = {
    controls: { hideNoControlsWarning: true },
};
