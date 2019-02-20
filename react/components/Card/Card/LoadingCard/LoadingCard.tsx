import * as React from "react";
import { LoadingImage } from "@schoolbox/Image/Image";
import { LoadingMeta } from "@schoolbox/Meta/Meta";

/**
 * Skeleton loader for a card.
 */
const LoadingCard: React.FunctionComponent = () => {
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
