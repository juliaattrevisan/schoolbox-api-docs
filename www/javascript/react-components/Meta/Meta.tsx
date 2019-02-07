import * as React from "react";

interface IMeta {
  children: string;
}

/**
 * Text that describes a particular item.
 */
const Meta: React.SFC<IMeta> = props => {
  const { children } = props;
  return <p className="meta">{children}</p>;
};

/**
 * Empty container element for creating a skeleton loader of meta text.
 */
const LoadingMeta: React.SFC = props => {
  return <p className="meta" />;
};

export { LoadingMeta, IMeta, Meta };
