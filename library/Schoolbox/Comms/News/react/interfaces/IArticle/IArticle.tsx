interface IArticleLinks {
  update: ILink;
  self: ILink;
}

interface IImage {
  _links: ILink;
}

interface IArticle {
  id: number;
  title: string;
  featureImage: IImage;
  status: string;
  statusDate: IDate;
  _links: IArticleLinks;
}

interface ILink {
  href: string;
  image: ILink;
}

interface IDate {
  long: string;
  relativeTime: string;
}

export { IArticle };
