import * as React from "react";
import { LoadingCard, CardList } from "@schoolbox/Card";

/**
 * Skeleton loader for a card list
 */
const LoadingCardList: React.FunctionComponent = () => {
  return (
    <CardList>
      <LoadingCard id="0" />
      <LoadingCard id="1" />
      <LoadingCard id="2" />
    </CardList>
  );
};

export { LoadingCardList };
