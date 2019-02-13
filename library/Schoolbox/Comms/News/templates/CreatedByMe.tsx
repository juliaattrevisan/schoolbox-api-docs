import axios from "axios";
import * as React from "react";
import { Pagination } from "../../../../../www/javascript/react-components/Pagination/Pagination";

import { Tab, Tabs } from "../../../../../www/javascript/react-components/Tabs/Tabs";
import { LoadingCard } from "../../../../../www/javascript/react-components/Cards/LoadingCard/LoadingCard";
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

                // Update the state with the statuses, articles & remove the loader.
                this.setState({
                    articlesLoading: false,
                    allArticles: articles,
                    statuses: statuses
                });
            })
            .catch(function (error) {
                console.log(error);
            });
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

            const defaultTabIndex = this.state.statuses.findIndex(
                status => status.name === this.props.initialTab
            );

            return (
                <Tabs defaultIndex={defaultTabIndex} onSelect={this.onTabClick}>
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
                initialPage={this.state.pageSelected}
                onPageChange={this.onPageClick}
            />
        );
    }

    private onPageClick = (data: any) => {
        this.setState({
            pageSelected: data.selected
        });
    };

    private onTabClick = (tab: number) => {
        this.setState({
            // Reset the page back to page 1
            pageSelected: 0,

            // Update tabSelected state
            tabSelected: tab
        });
    };
}