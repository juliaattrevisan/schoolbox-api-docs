import axios from "axios";
import React, { useState, useEffect } from "react";

import { Tabs, Tab, LoadingTabs } from "@schoolbox/Tabs";
import { LoadingCard } from "@schoolbox/Card";
import { Pagination } from "@schoolbox/Pagination/Pagination";
import { EmptyState } from "@schoolbox/EmptyState/EmptyState";
import { IArticle } from "../../interfaces/IArticle/IArticle";
import { NewsCardList } from "../../components/NewsCardList/NewsCardList";

interface ICreatedByMe {
  /** Page to show on load */
  initialPage: number;

  /** Tab to show on load */
  initialTab: string;

  /** Route for the API call for the list of articles */
  getAuthoredRoute: string;

  /** Route for the create form */
  createRoute: string;

  /** Amount of articles to show per page */
  articlesPerPage: number;

  /** Localised word for news */
  localeNews: string;

  /** Localised word for news in plural form */
  localeNewsPlural: string;
}

interface IStatus {
  name: string;
  count: number;
}

const CreatedByMe: React.FunctionComponent<ICreatedByMe> = ({
  initialPage,
  initialTab,
  getAuthoredRoute,
  articlesPerPage,
  createRoute,
  localeNews,
  localeNewsPlural
}) => {
  const [articlesLoading, setArticlesLoading] = useState<boolean>(true);
  const [allArticles, setAllArticles] = useState<IArticle[]>([]);
  const [allStatuses, setAllStatuses] = useState<IStatus[]>([]);
  const [pageSelected, setPageSelected] = useState<number>(initialPage);
  const [tabSelected, setTabSelected] = useState<number>(0);

  useEffect(() => {
    (async () => {
      // Get list of articles
      const [articles, statuses] = await fetchArticles(getAuthoredRoute);

      // Set the default tab
      const defaultTabIndex = statuses.findIndex(
        status => status.name === initialTab
      );

      // Update the state with the statuses, articles & remove the loader.
      setArticlesLoading(false);
      setAllArticles(articles);
      setAllStatuses(statuses);
      setTabSelected(defaultTabIndex);
    })();
  }, []);

  // Listen for window changes
  listenForWindow(allStatuses, setTabSelected, setPageSelected);

  return (
    <div>
      <section>
        {showLoaders(articlesLoading)}
        {showContent(
          articlesLoading,
          allArticles,
          allStatuses,
          pageSelected,
          tabSelected,
          articlesPerPage,
          createRoute,
          localeNews,
          localeNewsPlural,
          setTabSelected,
          setPageSelected
        )}
      </section>
      {showPagination(
        pageSelected,
        articlesPerPage,
        allStatuses,
        tabSelected,
        setPageSelected
      )}
    </div>
  );
};

const fetchArticles = async (
  route: string
): Promise<[IArticle[], IStatus[]]> => {
  const response = await axios.get(route);
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
    statuses.find((status: IStatus) => status.name === article.status).count++;
  });

  // Remove statuses that doesn't have any articles from the statuses array
  let i = statuses.length;
  while (i--) {
    if (statuses[i].count <= 0) {
      statuses.splice(i, 1);
    }
  }

  return [articles, statuses];
};

const listenForWindow = (
  statuses: IStatus[],
  setTabSelected: (tab: number) => void,
  setPageSelected: (page: number) => void
) => {
  window.onpopstate = event => {
    if (!event.state) {
      // Return to defaults
      setTabSelected(0);
      setPageSelected(1);
    } else {
      if (event.state.page !== undefined) {
        setPageSelected(event.state.page);
      }

      if (event.state.tab !== undefined) {
        const tabIndex = statuses.findIndex(
          status => status.name === event.state.tab
        );
        setTabSelected(tabIndex);
      }
    }
  };
};

const showContent = (
  articlesLoading: boolean,
  allArticles: IArticle[],
  statuses: IStatus[],
  pageSelected: number,
  tabSelected: number,
  articlesPerPage: number,
  createRoute: string,
  localeNews: string,
  localeNewsPlural: string,
  setTabSelected: (tab: number) => void,
  setPageSelected: (page: number) => void
): React.ReactNode => {
  if (articlesLoading) {
    return;
  }

  if (!allArticles) {
    return (
      <EmptyState icon="news" noMargin>
        <p>You haven't authored any {localeNewsPlural}.</p>
        <p>
          <a href={createRoute}>Create {localeNews}</a>.
        </p>
      </EmptyState>
    );
  }

  if (statuses.length) {
    const tabs: JSX.Element[] = [];

    statuses.map((status, index) => {
      if (status.count) {
        // Store all the articles for the selected status
        const statusArticles = allArticles.filter(article => {
          if (status.name === "All") {
            return true;
          }

          if (article.status === status.name) {
            return true;
          }
        });

        // Store all the articles for the selected status and page
        const limit = articlesPerPage;
        const offset = (pageSelected - 1) * limit;
        const total = offset + limit;
        const pageArticles = statusArticles.slice(offset, total);

        tabs.push(
          <Tab id={status.name} key={index} tab={status.name}>
            <NewsCardList articles={pageArticles} />
          </Tab>
        );
      }
    });

    const onSelect = (tab: number) => {
      onTabClick(tab, statuses, pageSelected, setTabSelected, setPageSelected);
    };

    return (
      <Tabs selectedIndex={tabSelected} onSelect={onSelect}>
        {tabs}
      </Tabs>
    );
  }
};

const showLoaders = (articlesLoading: boolean) => {
  if (!articlesLoading) {
    return;
  }

  return (
    <>
      <LoadingTabs />
      <LoadingCard />
      <LoadingCard />
      <LoadingCard />
    </>
  );
};

const showPagination = (
  pageSelected: number,
  articlesPerPage: number,
  statuses: IStatus[],
  tabSelected: number,
  setPageSelected: (page: number) => void
) => {
  if (!statuses[tabSelected]) {
    return;
  }

  const pageCount = statuses[tabSelected].count / articlesPerPage;

  if (pageCount <= 1) {
    return;
  }

  const onPageChange = (page: number) => {
    onPageClick(page, statuses, tabSelected, setPageSelected);
  };

  return (
    <Pagination
      pageCount={pageCount}
      onPageChange={onPageChange}
      forcePage={pageSelected}
    />
  );
};

/**
 * Changes page in React state & window history.
 * Used on click on a page.
 */
const onPageClick = (
  page: number,
  statuses: IStatus[],
  tabSelected: number,
  setPageSelected: (page: number) => void
) => {
  const tabName = statuses[tabSelected].name;

  // Update React state
  setPageSelected(page);

  // Update window history
  const params =
    tabSelected === 0 ? `?p=${page}` : `?p=${page}&status=${tabName}`;

  window.history.pushState({ page, tab: tabName }, "", params);
};

/**
 * Changes tab in React state & window history.
 * Used on click of a tab.
 */
const onTabClick = (
  tab: number,
  statuses: IStatus[],
  pageSelected: number,
  setTabSelected: (tab: number) => void,
  setPageSelected: (page: number) => void
) => {
  const tabName = statuses[tab].name;

  // Update tabSelected in React state (and reset page)
  setTabSelected(tab);
  setPageSelected(1);

  // Update the window history
  window.history.pushState(
    { tab: tabName, page: pageSelected },
    "",
    `?status=${tabName}`
  );
};

export default CreatedByMe;
