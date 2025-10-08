ARG RABBITMQ_MAJOR_VERSION=3.13

FROM rabbitmq:${RABBITMQ_MAJOR_VERSION}-management

RUN rabbitmq-plugins enable --offline \
    rabbitmq_management \
    rabbitmq_management_agent \
    rabbitmq_stomp \
    rabbitmq_web_dispatch
