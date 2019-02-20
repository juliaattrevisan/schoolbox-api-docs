import * as React from "react";
import ReactPaginate from "react-paginate";

interface IPagination {
  // The total number of pages.
  pageCount: number;

  // Selected page
  activePage?: number;

  // The method to call when a page is clicked. Exposes the current page object as an argument.
  onPageChange: (pageSelected: number) => void;
}

/**
 * Pagination.
 * Wraps an external package called [react-paginate](https://github.com/AdeleD/react-paginate)
 */
const Pagination: React.FunctionComponent<IPagination> = ({
  pageCount,
  onPageChange,
  activePage
}) => {
  // Take the activePage prop sent and convert it back to 0 index for the react-paginate plugin
  let pageZeroIndexed;
  if (activePage) {
    pageZeroIndexed = activePage - 1;
  }

  // Adjust the page change callback- add one to make the data 1 indexed.
  let onPageChangeOneIndexed;
  if (onPageChange) {
    onPageChangeOneIndexed = (selectedItem: { selected: number }) => {
      onPageChange(selectedItem.selected + 1);
    };
  }

  return (
    <ReactPaginate
      pageCount={pageCount}
      onPageChange={onPageChangeOneIndexed}
      pageRangeDisplayed={3}
      marginPagesDisplayed={1}
      containerClassName="pagination"
      previousLabel="‹"
      nextLabel="›"
      previousLinkClassName="arrow"
      nextLinkClassName="arrow"
      disabledClassName="unavailable"
      activeClassName="active"
      disableInitialCallback={true}
      forcePage={pageZeroIndexed}
    />
  );
};

export { Pagination };
