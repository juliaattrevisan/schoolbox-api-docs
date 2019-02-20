import * as React from "react";
import { LoadingCard, CardList } from "@schoolbox/Card";

/**
 * Skeleton loader for a card list
 */
const LoadingCardList: React.FunctionComponent = () => {
  return (
    <CardList>
      <LoadingCard />
      <LoadingCard />
      <LoadingCard />
    </CardList>
  );
};

export { LoadingCardList };
