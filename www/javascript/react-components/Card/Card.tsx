import * as React from "react";
import { IImage, Image } from "../Image/Image";
import { Meta } from "../Meta/Meta";

interface ICard {
  /** Title to display on the card */
  title: string;

  /** Meta information to display below the title */
  meta: string | string[];
  // ^Ideally this would be Meta | Meta[] instead of string, but that would require us to send
  // meta={{children: "Some meta" }} instead of just meta="Some meta", so I'm leaving this for now.

  /** Adds a link to the image and title of the card */
  href?: string;

  /** URL of the image to display on the card */
  image?: IImage;
}

/**
 * Flexible container for small amounts of content, often displayed in a list.
 */
const Card: React.SFC<ICard> = props => {
  const { title, meta, image } = props;

  // Determine if the card has some sort of content
  // Used to decide whether to show loaders or not
  const hasContent = title || meta || image;

  if (hasContent) {
    return (
      <div className="list-item">
        <div className="small-12 card">
          <CardImage {...props} />
          <CardTitle {...props} />
          <CardMeta {...props} />
        </div>
      </div>
    );
  }

  return null;
};

const CardImage: React.SFC<ICard> = props => {
  const { image, href } = props;
  if (image && href) {
    return (
      <a href={href}>
        <Image src={image.src} alt={image.alt} />
      </a>
    );
  }

  if (image) {
    return <Image src={image.src} alt={image.alt} />;
  }

  return null;
};

const CardTitle: React.SFC<ICard> = props => {
  const { title, href } = props;

  if (title && href) {
    return (
      <h3>
        <a href={href}>{title}</a>
      </h3>
    );
  }

  if (title) {
    return <h3>{title}</h3>;
  }

  return null;
};

const CardMeta: React.SFC<ICard> = props => {
  const { meta } = props;

  if (meta && Array.isArray(meta)) {
    return (
      <>
        {meta.map((thisMeta, index) => (
          <Meta key={index}>{thisMeta}</Meta>
        ))}
      </>
    );
  }

  if (meta && !Array.isArray(meta)) {
    return <Meta>{meta}</Meta>;
  }

  return null;
};

export { ICard, Card };
