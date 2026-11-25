<?php

namespace App\Database;

use Illuminate\Database\Connectors\PostgresConnector;

class PostgresFastConnector extends PostgresConnector
{
    /**
     * Create a DSN string from a configuration.
     *
     * Cuando search_path_in_dsn está activo y los esquemas son identificadores
     * simples válidos, se envía search_path en el paquete de inicio (libpq options)
     * para evitar el viaje de red adicional de "SET search_path" tras conectar.
     *
     * @return string
     */
    protected function getDsn(array $config)
    {
        $dsn = parent::getDsn($config);

        $searchPathOption = $this->resolverSearchPathParaDsn($config);

        if ($searchPathOption !== null) {
            $dsn .= ";options='--search_path={$searchPathOption}'";
        }

        return $dsn;
    }

    /**
     * Set the "search_path" on the database connection.
     *
     * Si search_path ya fue enviado en el DSN de inicio, se omite el viaje SQL
     * redundante. En cualquier otro caso se delega sin cambios a Laravel.
     *
     * @param  \PDO  $connection
     * @param  array  $config
     * @return void
     */
    protected function configureSearchPath($connection, $config)
    {
        if ($this->resolverSearchPathParaDsn($config) !== null) {
            return;
        }

        parent::configureSearchPath($connection, $config);
    }

    /**
     * Valida y devuelve la lista de esquemas segura para enviar en el DSN,
     * o null si la optimización está desactivada o el valor no es trivial.
     */
    protected function resolverSearchPathParaDsn(array $config): ?string
    {
        if (empty($config['search_path_in_dsn'])) {
            return null;
        }

        if (! isset($config['search_path']) && ! isset($config['schema'])) {
            return null;
        }

        $schemas = $this->parseSearchPath($config['search_path'] ?? $config['schema']);

        if ($schemas === []) {
            return null;
        }

        foreach ($schemas as $schema) {
            if (! is_string($schema) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $schema)) {
                return null;
            }
        }

        return implode(',', $schemas);
    }
}
