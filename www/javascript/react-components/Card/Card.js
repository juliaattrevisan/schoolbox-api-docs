import * as React from "react";

/**
 * Flexible container for small amounts of content, often displayed in a list.
 */
const Card = props => {
  const { title, meta, image, loader, children } = props;

  // Determine if the card has some sort of content
  // Used to decide whether to show loaders or not
  const hasContent = title || meta || image || children;

  if (!hasContent && loader) {
    return <CardLoader />;
  } else if (hasContent) {
    return (
      <>
        <div className="list-item">
          <div className="small-12 card">
            <CardImage {...props} />
            <CardTitle {...props} />
            <CardMeta {...props} />
          </div>
        </div>
        <CardActions {...props} />
        <CardContent {...props} />
      </>
    );
  }

  return null;
};

Card.defaultProps = {
  loader: true
};

const CardLoader = () => {
  return (
    <div className="list-item">
      <div className="small-12 card skeleton">
        <div className="img" />
        <h3 />
        <p className="meta" />
      </div>
    </div>
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
  if (meta) {
    return <p className="meta">{meta}</p>;
  }

  return null;
};

const CardActions = props => {
  const { actions } = props;
  if (actions && actions.length) {
    return (
      <nav>
        {actions.map((action, index) => (
          <a
            key={index}
            href={action.href}
            className={`icon-${action.icon} no-text`}
            title={action.title}
          />
        ))}
      </nav>
    );
  }

  return null;
};

const CardContent = props => {
  const { children } = props;
  if (children) {
    return <article>{children}</article>;
  }

  return null;
};

export default Card;
