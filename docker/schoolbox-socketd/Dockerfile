ARG ARCH=amd64

FROM --platform=linux/${ARCH} php82-bookworm

WORKDIR /usr/share/schoolbox

CMD php /usr/share/schoolbox/tools/socket/socketd.php -vvv -p9081
