# Presidio Analyzer Configuration

This directory contains custom configuration files for the Presidio Analyzer container build.

## nlp.yaml

The `nlp.yaml` file configures the NLP engine used by Presidio Analyzer. This file is copied into the Docker image during the build process and replaces the default Presidio configuration.

### Expected Location
`docker/presidio-analyzer/config/nlp.yaml`

### Format
The file should follow the Presidio NLP configuration format. Example:

```yaml
nlp_engine_name: spacy
models:
  - lang_code: en
    model_name: en_core_web_lg

ner_model_configuration:
  model_to_presidio_entity_mapping:
    PER: PERSON
    PERSON: PERSON
    NORP: NRP
    FAC: LOCATION
    LOC: LOCATION
    LOCATION: LOCATION
    GPE: LOCATION
    ORG: ORGANIZATION
    ORGANIZATION: ORGANIZATION
    DATE: DATE_TIME
    TIME: DATE_TIME

  low_confidence_score_multiplier: 0.4
  low_score_entity_names: []
  labels_to_ignore:
    - CARDINAL
    - EVENT
    - LANGUAGE
    - LAW
    - MONEY
    - ORDINAL
    - PERCENT
    - PRODUCT
    - QUANTITY
    - WORK_OF_ART
```

### Build Process
When the GitHub Actions workflow builds the Presidio Analyzer image, it:
1. Clones the microsoft/presidio repository (main branch)
2. Copies this `nlp.yaml` file to `presidio-analyzer/presidio_analyzer/conf/nlp.yaml`
3. Builds the Docker image with the build arg: `NLP_CONF_FILE=presidio_analyzer/conf/nlp.yaml`

### Automatic Builds
Changes to `nlp.yaml` on the `master` or `develop` branches automatically trigger a new build.

For more information on Presidio NLP configuration, see:
https://github.com/microsoft/presidio/tree/main/presidio-analyzer
