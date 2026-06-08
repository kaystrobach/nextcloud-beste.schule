<?php
declare(strict_types=1);

namespace OCA\BesteSchule\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20260608170000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('besteschule_sync_logs')) {
            $table = $schema->createTable('besteschule_sync_logs');
            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull'       => true,
            ]);
            $table->addColumn('account_id', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('level', Types::STRING, [
                'notnull' => true,
                'length'  => 16,
                'default' => 'info',
            ]);
            $table->addColumn('message', Types::TEXT, [
                'notnull' => true,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['account_id'], 'bs_logs_account_idx');
        }

        return $schema;
    }
}
