import * as React from "react";
import { Card, CardList } from "@schoolbox/Card";
import { IAction } from "@schoolbox/Actions";
import { IArticle } from "../../interfaces/IArticle/IArticle";

interface INewsCardList {
  articles: IArticle[];
}

const NewsCardList: React.FunctionComponent<INewsCardList> = ({ articles }) => {
  return (
    <CardList>
      {articles.map(article => (
        <Card
          key={article.id}
          id={article.id}
          title={article.title}
          meta={generateMeta(article)}
          href={article._links && article._links.self.href}
          actions={generateCardActions(article)}
          image={{
            src:
              article.featureImage &&
              `${article.featureImage._links.image.href}&size=square64`,
            alt: ""
          }}
        />
      ))}
    </CardList>
  );
};

const generateCardActions = (article: IArticle): IAction[] | null => {
  if (article._links && article._links.update) {
    return [
      {
        id: 1,
        title: "Modify",
        href: article._links.update.href,
        icon: "modify"
      }
    ];
  }

  return null;
};

const generateMeta = (article: IArticle): JSX.Element => {
  return (
    <>
      <b>{article.status}</b>{" "}
      <time title={article.statusDate.long}>
        {article.statusDate.relativeTime}
      </time>
    </>
  );
};

export { NewsCardList, INewsCardList };
