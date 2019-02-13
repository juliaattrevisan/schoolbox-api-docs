import * as React from "react";
import { InformationList } from "../../InformationList/InformationList";
import { InformationListItem } from "../../InformationList/InformationListItem/InformationListItem";

interface ICardList {
    children: any;
}

/**
 * A list of cards.
 */
const CardList: React.SFC<ICardList> = props => {
    // Wrap the cards in InformationListItem's
    const Cards = props.children.map((card: any) => {
        return (
            <InformationListItem key={card.props.id} actions={card.props.actions && card.props.actions.length}>
                {card}
            </InformationListItem>
        );
    });

    return (
        <InformationList>
            {Cards}
        </InformationList>
    );
}

export { CardList, ICardList };