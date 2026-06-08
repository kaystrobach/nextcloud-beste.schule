<?php

declare(strict_types=1);

namespace OCA\BesteSchule\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20260608155700 extends SimpleMigrationStep
{
    /**
     * @param IOutput $output
     * @param Closure $schemaClosure
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $tables = ['besteschule_grades', 'besteschule_finalgrades'];

        foreach ($tables as $tableName) {
            if ($schema->hasTable($tableName)) {
                $table = $schema->getTable($tableName);
                $column = $table->getColumn('value');
                $column->setDefault('');
            }
        }

        return $schema;
    }
}
