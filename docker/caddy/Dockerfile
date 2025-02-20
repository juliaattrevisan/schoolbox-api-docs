FROM caddy:alpine

COPY Caddyfile /etc/caddy/Caddyfile

#RUN chown -R caddy:caddy /usr/share/schoolbox
#
#USER caddy

EXPOSE 80 443

WORKDIR /usr/share/schoolbox

CMD ["caddy", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
