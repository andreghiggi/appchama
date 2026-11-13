const { aplicar } = require('../shared/marca-config');

// app.json traz o que é igual para todas as marcas; o resto vem de marcas/<MARCA>/marca.json.
module.exports = ({ config }) => aplicar(config, 'driver', __dirname);
