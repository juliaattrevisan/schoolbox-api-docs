ARG ARCH=amd64

FROM --platform=linux/${ARCH} php82-ubuntu

WORKDIR /usr/share/schoolbox

CMD php /usr/share/schoolbox/tools/socket/socketd.php -vvv -p9081
