<?php

namespace App\Database;

use DateTimeInterface;
use Illuminate\Database\PostgresConnection;
use PDO;

class PostgresEmulatedConnection extends PostgresConnection
{
    /**
     * Prepare the query bindings for execution.
     *
     * Cuando PDO::ATTR_EMULATE_PREPARES está activo en PostgreSQL,
     * convertir los valores booleanos a 'true'/'false' evita el error
     * SQLSTATE[42883] (operator does not exist: boolean = integer).
     * Cuando la emulación está desactivada, se delega sin cambios a Laravel.
     *
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
        if (! $this->usaPreparesEmulados()) {
            return parent::prepareBindings($bindings);
        }

        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            } elseif (is_bool($value)) {
                $bindings[$key] = $value ? 'true' : 'false';
            }
        }

        return $bindings;
    }

    /**
     * Determina si la conexión utiliza prepares emulados de PDO.
     */
    protected function usaPreparesEmulados(): bool
    {
        if ($this->pdo instanceof PDO) {
            return (bool) $this->pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        }

        $options = $this->getConfig('options');

        return is_array($options)
            && ! empty($options[PDO::ATTR_EMULATE_PREPARES]);
    }
}
