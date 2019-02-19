import * as React from "react";
import ReactPaginate from "react-paginate";

interface IPagination {
  // The total number of pages.
  pageCount: number;

  // The initial page selected.
  initialPage?: number;

  forcePage?: number;

  // The method to call when a page is clicked. Exposes the current page object as an argument.
  onPageChange?(selectedItem: { selected: number }): void;
}

/**
 * Pagination.
 * Wraps an external package called [react-paginate](https://github.com/AdeleD/react-paginate)
 */
const Pagination: React.FunctionComponent<IPagination> = props => (
  <ReactPaginate
    pageCount={props.pageCount}
    onPageChange={props.onPageChange}
    initialPage={props.initialPage}
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
    forcePage={props.forcePage}
  />
);

export { Pagination };
