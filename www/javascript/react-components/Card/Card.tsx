import * as React from "react";

interface ICard {
  /** Whether to show a loading skeleton or not when there's no content. */
  loader?: boolean;

  /** Title to display on the card */
  title?: string;

  /** Meta information to display below the title */
  meta?: string | string[];

  /** Adds a link to the image and title of the card */
  href?: string;

  /** URL of the image to display on the card */
  image?: string;
}

/**
 * Flexible container for small amounts of content, often displayed in a list.
 */
const Card: React.SFC<ICard> = props => {
  const { title, meta, image, loader } = props;

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
  } else if (loader) {
    return <CardLoader />;
  }

  return null;
};

Card.defaultProps = {
  loader: true
};

const CardLoader: React.SFC = () => {
  return (
    <>
      <div className="list-item">
        <div className="small-12 card skeleton">
          <div className="img" />
          <h3 />
          <p className="meta" />
        </div>
      </div>
    </>
  );
};

const CardImage: React.SFC<ICard> = props => {
  const { image, href } = props;
  if (image && href) {
    return (
      <a href={href}>
        <img src={image} />
      </a>
    );
  } else if (image) {
    return <img src={image} />;
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
  } else if (title) {
    return <h3>{title}</h3>;
  }

  return null;
};

const CardMeta: React.SFC<ICard> = props => {
  const { meta } = props;
  if (meta && Array.isArray(meta)) {
    return (
      <>
        {meta.map(thisMeta => (
          <p className="meta">{thisMeta}</p>
        ))}
      </>
    );
  } else if (meta) {
    return <p className="meta">{meta}</p>;
  }

  return null;
};

export default Card;
