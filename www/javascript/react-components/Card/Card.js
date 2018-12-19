import * as React from "react";

/**
 * Flexible container for small amounts of content, often displayed in a list.
 */
const Card = props => {
  const cardImage = props.image ? <img src={props.image} /> : undefined;

  return (
    <>
      <div className="list-item">
        <div className="card small-12">
          {cardImage}
          <h3>{props.title}</h3>
          <p className="meta">{props.meta}</p>
        </div>
      </div>
      <article>{props.children}</article>
    </>
  );
};

export default Card;
