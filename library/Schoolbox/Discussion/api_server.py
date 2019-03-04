import connexion

app = connexion.FlaskApp(__name__)
app.add_api('api.yaml')
app.run(port=8080)
