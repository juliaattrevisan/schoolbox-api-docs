import * as React from "react";
import { LoadingImage } from "@schoolbox/Image/Image";
import { LoadingMeta } from "@schoolbox/Meta/Meta";

interface ILoadingCard {
  /** Unique identifier: required when using inside a CardList (uses it for a key) */
  id?: string;
}

/**
 * Skeleton loader for a card.
 */
const LoadingCard: React.FunctionComponent<ILoadingCard> = () => {
  return (
    <>
      <div className="list-item">
        <div className="small-12 card skeleton">
          <LoadingImage />
          <h3 />
          <LoadingMeta />
        </div>
      </div>
    </>
  );
};

export { LoadingCard };
