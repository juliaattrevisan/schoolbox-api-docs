import * as React from "react";
import { LoadingCard } from "@schoolbox/Card";

/**
 * Skeleton loader for a card list
 */
const LoadingCardList: React.FunctionComponent = () => {
  return (
    <>
      <LoadingCard />
      <LoadingCard />
      <LoadingCard />
    </>
  );
};

export { LoadingCardList };
