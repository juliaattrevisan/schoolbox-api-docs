import * as React from "react";

/**
 * Flexible container for small amounts of content, often displayed in a list.
 */
const Card = props => {
  const { title, meta, image, loader, children } = props;

  // Determine if the card has some sort of content
  // Used to decide whether to show loaders or not
  const hasContent = title || meta || image || children;

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

const CardLoader = () => {
  return (
    <>
      <div className="list-item">
        <div className="small-12 card skeleton">
          <div className="img" />
          <h3 />
          <p className="meta" />
        </div>
      </div>
      <article></article>
    </>
  );
};

const CardImage = props => {
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

const CardTitle = props => {
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

const CardMeta = props => {
  const { meta } = props;
  if (meta && Array.isArray(meta)) {
    return meta.map(thisMeta => <p className="meta">{thisMeta}</p>);
  } else if (meta) {
    return <p className="meta">{meta}</p>;
  }

  return null;
};

export default Card;
