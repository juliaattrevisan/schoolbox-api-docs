import * as React from "react";

interface IImage {
  src: string;
  alt: string;
}

/**
 * Just a basic image element. Requires alt text for accessibility.
 */
const Image: React.SFC<IImage> = props => {
  const { src, alt } = props;
  return <img src={src} alt={alt} />;
};

/**
 * Empty container element for creating a skeleton loader of meta text.
 */
const LoadingImage: React.SFC = props => {
  return <div className="img" />;
};

export { LoadingImage, IImage, Image };
