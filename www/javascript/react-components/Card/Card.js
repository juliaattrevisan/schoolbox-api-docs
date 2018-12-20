import * as React from "react";
import classnames from "classnames";

/**
 * Flexible container for small amounts of content, often displayed in a list.
 */
const Card = props => {
  const { title, meta, image, children } = props;
  let imageElement, titleElement, metaElement, actionsElement, contentElement;

  // Determine if the card has some sort of content
  // Used to decide whether to show loaders or not
  const hasContent = title || meta || image || children;

  const cardClassses = classnames("small-12", "card", {
    skeleton: !hasContent
  });

  if (hasContent) {
    imageElement = image && <img src={image} />;
    titleElement = title && <h3>{title}</h3>;
    metaElement = meta && <p className="meta">{meta}</p>;
    contentElement = children && <article>{children}</article>;
  } else {
    // Populate elements for the loading state
    imageElement = <div className="img" />;
    titleElement = <h3 />;
    metaElement = <p className="meta" />;
  }

  return (
    <>
      <div className="list-item">
        <div className={cardClassses}>
          {imageElement}
          {titleElement}
          {metaElement}
        </div>
      </div>
      {contentElement}
    </>
  );
};

export default Card;
