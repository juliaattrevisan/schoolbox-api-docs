FROM mcr.microsoft.com/presidio-analyzer

ENV NLP_CONF_FILE=config/nlp.yaml

# Reinstall the actually requested models from the conf file,
# ensuring that they are included in the distributable image
RUN mkdir config
COPY ./config/* config/
RUN poetry run python install_nlp_models.py --conf_file ${NLP_CONF_FILE}
