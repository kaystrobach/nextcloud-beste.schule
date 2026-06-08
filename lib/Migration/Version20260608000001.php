<?php
declare(strict_types=1);

namespace OCA\BesteSchule\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20260608000001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        $table = $schema->getTable('besteschule_accounts');

        if (!$table->hasColumn('address')) {
            $table->addColumn('address', Types::STRING, [
                'notnull' => false,
                'length'  => 255,
            ]);
        }

        return $schema;
    }
}
