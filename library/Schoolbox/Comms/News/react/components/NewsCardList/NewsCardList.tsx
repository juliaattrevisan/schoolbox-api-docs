import * as React from "react";
import { Card, CardList } from "@schoolbox/Card";
import { IAction } from "@schoolbox/Actions/Action/Action";
import IArticle from "../../interfaces/IArticle/IArticle";

interface INewsCardList {
    articles: IArticle[];
}

export class NewsCardList extends React.Component<INewsCardList> {
    public render() {
        return (
            <CardList>
                {this.props.articles.map(article => (
                    <Card
                        key={article.id}
                        id={article.id}
                        title={article.title}
                        meta={this.generateMeta(article)}
                        href={article._links && article._links.self.href}
                        actions={this.generateCardActions(article)}
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
    }

    private generateCardActions(article: IArticle): IAction[] | null {
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
    }

    private generateMeta(article: IArticle): JSX.Element {
        return (
            <>
                <b>{article.status}</b>
                &nbsp;
                <time title={article.statusDate.long}>
                    {article.statusDate.relativeTime}
                </time>
            </>
        );
    }
}