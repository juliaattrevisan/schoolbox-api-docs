import axios from "axios";
import * as React from "react";

import { Tab, Tabs } from "../../../../../www/javascript/react-components/Tabs/Tabs";
import { LoadingCard } from "../../../../../www/javascript/react-components/Cards/LoadingCard/LoadingCard";
import { Pagination } from "../../../../../www/javascript/react-components/Pagination/Pagination";
import IArticle from "../react-components/IArticle/IArticle";
import { NewsCardList } from "../react-components/NewsCardList/NewsCardList";

interface ICreatedByMe {
    /** Page to show on load */
    initialPage: number;

    /** Tab to show on load */
    initialTab: string;

    /** Route for the API call for the list of articles */
    getAuthoredRoute: string;

    /** Amount of articles to show per page */
    articlesPerPage: number;

    /** Localised word for news */
    localeNews: string;

    /** Localised word for news in plural form */
    localeNewsPlural: string;
}

interface ICreatedByMeState {
    /** Every article returned from the API */
    allArticles: IArticle[];

    /** The list of articles for the current page and tab filter */
    currentArticles: IArticle[];

    /** Loading bool while the XHR is happening */
    articlesLoading: boolean;

    /** The article statuses (draft, published, etc) to show tabs for */
    statuses: IStatus[];

    /** The current page of pagination */
    pageSelected: number;

    /** The current tab showing */
    tabSelected: number;
}

interface IStatus {
    name: string;
    count: number;
}

export default class CreatedByMe extends React.Component<
    ICreatedByMe,
    ICreatedByMeState
    > {
    constructor(props: any) {
        super(props);
        this.state = {
            allArticles: [],
            currentArticles: [],
            articlesLoading: true,
            statuses: [],
            pageSelected: this.props.initialPage,
            tabSelected: 0
        };
    }

    public render() {
        return (
            <div>
                <section>
                    {this.showLoaders()}
                    {this.showContent()}
                </section>
                {this.showPagination()}
            </div>
        );
    }

    public componentDidMount() {
        // Fetch articles created by the logged in user
        axios
            .get(this.props.getAuthoredRoute)
            .then(response => {
                const articles = response.data;
                const statuses = [
                    {
                        name: "All",
                        count: 0
                    },
                    {
                        name: "Drafted",
                        count: 0
                    },
                    {
                        name: "Awaiting Moderation",
                        count: 0
                    },
                    {
                        name: "Rejected",
                        count: 0
                    },
                    {
                        name: "Awaiting Publication",
                        count: 0
                    },
                    {
                        name: "Published",
                        count: 0
                    },
                    {
                        name: "Archived",
                        count: 0
                    }
                ];

                // Loop through the articles and set status counts (for deciding whether to show tabs or not)
                articles.forEach((article: IArticle) => {
                    // Increment the 'all' status
                    statuses.find((status: IStatus) => status.name === "All").count++;

                    // Increment the specific status
                    statuses.find((status: IStatus) => status.name === article.status)
                        .count++;
                });

                // Remove statuses that doesn't have any articles from the statuses array
                let i = statuses.length;
                while (i--) {
                    if (statuses[i].count <= 0) {
                        statuses.splice(i, 1);
                    }
                }

                // Set the default tab
                const defaultTabIndex = statuses.findIndex(
                    status => status.name === this.props.initialTab
                );

                // Update the state with the statuses, articles & remove the loader.
                this.setState({
                    articlesLoading: false,
                    allArticles: articles,
                    statuses: statuses,
                    tabSelected: defaultTabIndex
                });
            })
            .catch(function (error) {
                console.log(error);
            });

        // Listen for window state change
        const self = this;
        window.onpopstate = (event) => {
            if (!event.state) {
                // Return to defaults
                self.changeTabState(0, true);
            } else {
                if (event.state.page !== undefined) {
                    self.changePageState(event.state.page - 1);
                }

                if (event.state.tab !== undefined) {
                    const tabIndex = self.state.statuses.findIndex(status => status.name === event.state.tab);
                    self.changeTabState(tabIndex);
                }
            }
        };
    }

    private showContent(): React.ReactNode {
        if (!this.state.allArticles.length) {
            return;
        }

        if (this.state.statuses.length) {
            const tabs: JSX.Element[] = [];

            this.state.statuses.map((status, index) => {
                if (status.count) {
                    // Store all the articles for the selected status
                    const statusArticles = this.state.allArticles.filter(article => {
                        if (status.name === "All") {
                            return true;
                        }

                        if (article.status === status.name) {
                            return true;
                        }
                    });

                    // Store all the articles for the selected status and page
                    const limit = this.props.articlesPerPage;
                    const offset = this.state.pageSelected * limit;
                    const total = offset + limit;
                    const pageArticles = statusArticles.slice(offset, total);

                    tabs.push(
                        <Tab id={status.name} key={index} tab={status.name}>
                            <NewsCardList articles={pageArticles} />
                        </Tab>
                    );
                }
            });

            return (
                <Tabs selectedIndex={this.state.tabSelected} onSelect={this.onTabClick}>
                    {tabs}
                </Tabs>
            );
        }
    }

    private showLoaders() {
        if (!this.state.articlesLoading) {
            return;
        }

        return (
            <>
                <LoadingCard />
                <LoadingCard />
                <LoadingCard />
            </>
        );
    }

    private showPagination() {
        if (!this.state.statuses[this.state.tabSelected]) {
            return;
        }

        const pageCount = this.state.statuses[this.state.tabSelected].count / this.props.articlesPerPage;

        if (pageCount <= 1) {
            return;
        }

        return (
            <Pagination
                pageCount={pageCount}
                onPageChange={this.onPageClick}
                forcePage={this.state.pageSelected}
            />
        );
    }

    /**
     * Changes page in React state & window history.
     * Used on click on a page.
     */
    private onPageClick = (data: any) => {
        const page = data.selected;
        const tabName = this.state.statuses[this.state.tabSelected].name;

        // Update React state
        this.changePageState(page);

        // Update window history
        const params = (this.state.tabSelected === 0) ? `?p=${page}` : `?p=${page}&status=${tabName}`;
        window.history.pushState({ page: page + 1, tab: tabName }, '', params);
    };

    /**
     * Changes tab in React state & window history.
     * Used on click of a tab.
     */
    private onTabClick = (tab: number) => {
        const tabName = this.state.statuses[tab].name;

        // Update React state
        this.changeTabState(tab, true);

        // Update the window history
        window.history.pushState(
            { tab: tabName, page: this.state.pageSelected + 1 },
            '',
            `?status=${tabName}`
        );
    }

    /**
     * Changes tab in React state only.
     * Used when listening for window state history and adjusting tabs (doesn't want to update window history)
     */
    private changeTabState = (tab: number, resetPage: boolean = false) => {
        const newState = {
            tabSelected: tab
        };

        if (resetPage) {
            Object.assign(newState, { pageSelected: 0 });
        }

        this.setState(newState);
    };

    /**
     * Changes page in React state only.
     */
    private changePageState = (page: number) => {
        this.setState({
            pageSelected: page
        });
    }
}