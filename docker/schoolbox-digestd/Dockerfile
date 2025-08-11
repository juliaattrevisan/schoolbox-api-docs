ARG ARCH=amd64

FROM --platform=linux/${ARCH} php84-bookworm

WORKDIR /usr/share/schoolbox

CMD php /usr/share/schoolbox/tools/digest/digestd.php -vvv
