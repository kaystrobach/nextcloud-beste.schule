<?php
declare(strict_types=1);

namespace OCA\BesteSchule\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20260608000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // ── accounts ──────────────────────────────────────────────────────────
        if (!$schema->hasTable('besteschule_accounts')) {
            $table = $schema->createTable('besteschule_accounts');
            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull'       => true,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length'  => 64,
            ]);
            $table->addColumn('access_token', Types::TEXT, [
                'notnull' => true,
            ]);
            $table->addColumn('student_id', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('student_name', Types::STRING, [
                'notnull' => true,
                'length'  => 255,
            ]);
            $table->addColumn('interval_id', Types::INTEGER, [
                'notnull'  => true,
                'default'  => 0,
            ]);
            $table->addColumn('calendar_uri', Types::STRING, [
                'notnull' => false,
                'length'  => 255,
            ]);
            $table->addColumn('sync_interval', Types::INTEGER, [
                'notnull' => true,
                'default' => 24,
                'comment' => 'Hours between background syncs',
            ]);
            $table->addColumn('last_sync_at', Types::DATETIME, [
                'notnull' => false,
            ]);
            $table->addColumn('last_sync_error', Types::TEXT, [
                'notnull' => false,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'bs_accounts_user_idx');
        }

        // ── grades ────────────────────────────────────────────────────────────
        if (!$schema->hasTable('besteschule_grades')) {
            $table = $schema->createTable('besteschule_grades');
            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull'       => true,
            ]);
            $table->addColumn('account_id', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('external_id', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('value', Types::STRING, [
                'notnull' => true,
                'length'  => 16,
            ]);
            $table->addColumn('given_at', Types::DATE, [
                'notnull' => false,
            ]);
            $table->addColumn('subject_id', Types::INTEGER, [
                'notnull' => false,
            ]);
            $table->addColumn('subject_name', Types::STRING, [
                'notnull' => true,
                'length'  => 255,
            ]);
            $table->addColumn('collection_name', Types::STRING, [
                'notnull' => false,
                'length'  => 255,
            ]);
            $table->addColumn('teacher_name', Types::STRING, [
                'notnull' => false,
                'length'  => 255,
            ]);
            $table->addColumn('weight', Types::DECIMAL, [
                'notnull'   => false,
                'precision' => 5,
                'scale'     => 2,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['account_id'], 'bs_grades_account_idx');
            $table->addUniqueIndex(['account_id', 'external_id'], 'bs_grades_ext_unique');
        }

        // ── final grades ──────────────────────────────────────────────────────
        if (!$schema->hasTable('besteschule_finalgrades')) {
            $table = $schema->createTable('besteschule_finalgrades');
            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull'       => true,
            ]);
            $table->addColumn('account_id', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('external_id', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('subject_name', Types::STRING, [
                'notnull' => true,
                'length'  => 255,
            ]);
            $table->addColumn('interval_id', Types::INTEGER, [
                'notnull' => false,
            ]);
            $table->addColumn('interval_name', Types::STRING, [
                'notnull' => false,
                'length'  => 255,
            ]);
            $table->addColumn('value', Types::STRING, [
                'notnull' => true,
                'length'  => 16,
            ]);
            $table->addColumn('value_calc', Types::STRING, [
                'notnull' => false,
                'length'  => 16,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['account_id'], 'bs_finalgrades_account_idx');
            $table->addUniqueIndex(['account_id', 'external_id'], 'bs_finalgrades_ext_unique');
        }

        return $schema;
    }
}
