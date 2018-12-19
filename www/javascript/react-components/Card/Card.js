import * as React from "react";

/**
 * Flexible container for small amounts of content, often displayed in a list.
 */
const Card = props => {
  return (
    <>
      <div className="list-item">
        <div className="card small-12">
          <h3>{props.title}</h3>
          <p className="meta">{props.meta}</p>
        </div>
      </div>
      <article>{props.children}</article>
    </>
  );
};

export default Card;
