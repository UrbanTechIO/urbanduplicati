<?php

declare(strict_types=1);

namespace OCA\UrbanDuplicati\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Initial database schema for UrbanDuplicati.
 *
 * Creates the following tables (Nextcloud prepends the configured prefix,
 * typically "oc_", so e.g. "ud_tasks" becomes "oc_ud_tasks"):
 *
 *   ud_tasks        — scan jobs
 *   ud_groups       — duplicate groups found by a scan
 *   ud_group_files  — individual files belonging to a duplicate group
 *   ud_protection   — folders/paths that must never be auto-deleted
 *   ud_audit        — log of every file deletion performed by the app
 *   ud_hash_cache   — cached perceptual hashes to speed up re-scans
 */
class Version010000Date20260525000000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // ── ud_tasks ──────────────────────────────────────────────────────
        if (!$schema->hasTable('ud_tasks')) {
            $table = $schema->createTable('ud_tasks');
            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull'       => true,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length'  => 64,
            ]);
            $table->addColumn('name', Types::STRING, [
                'notnull' => true,
                'length'  => 255,
            ]);
            $table->addColumn('created_time', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
            ]);
            $table->addColumn('target_directory_ids', Types::TEXT, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('collector_settings', Types::TEXT, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('files_scanned', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
            ]);
            $table->addColumn('files_total', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
            ]);
            $table->addColumn('files_total_size', Types::BIGINT, [
                'notnull' => true,
                'default' => 0,
            ]);
            $table->addColumn('py_pid', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
            ]);
            $table->addColumn('errors', Types::TEXT, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('finished_time', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'ud_tasks_user_id_idx');
        }

        // ── ud_groups ─────────────────────────────────────────────────────
        if (!$schema->hasTable('ud_groups')) {
            $table = $schema->createTable('ud_groups');
            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull'       => true,
            ]);
            $table->addColumn('task_id', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('group_id', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('hash', Types::STRING, [
                'notnull' => false,
                'length'  => 256,
                'default' => null,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['task_id'], 'ud_groups_task_id_idx');
        }

        // ── ud_group_files ────────────────────────────────────────────────
        if (!$schema->hasTable('ud_group_files')) {
            $table = $schema->createTable('ud_group_files');
            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull'       => true,
            ]);
            $table->addColumn('task_id', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('group_id', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('fileid', Types::BIGINT, [
                'notnull' => true,
            ]);
            $table->addColumn('filename', Types::STRING, [
                'notnull' => false,
                'length'  => 255,
                'default' => null,
            ]);
            $table->addColumn('filepath', Types::TEXT, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('filesize', Types::BIGINT, [
                'notnull' => false,
                'default' => 0,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['task_id'], 'ud_group_files_task_id_idx');
            $table->addIndex(['task_id', 'group_id'], 'ud_group_files_task_group_idx');
        }

        // ── ud_protection ─────────────────────────────────────────────────
        if (!$schema->hasTable('ud_protection')) {
            $table = $schema->createTable('ud_protection');
            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull'       => true,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length'  => 64,
            ]);
            $table->addColumn('path', Types::TEXT, [
                'notnull' => true,
            ]);
            $table->addColumn('label', Types::STRING, [
                'notnull' => false,
                'length'  => 255,
                'default' => null,
            ]);
            $table->addColumn('is_recursive', Types::SMALLINT, [
                'notnull' => true,
                'default' => 1,
            ]);
            $table->addColumn('scope', Types::STRING, [
                'notnull' => true,
                'length'  => 16,
                'default' => 'user',
            ]);
            $table->addColumn('created_at', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'ud_protection_user_id_idx');
        }

        // ── ud_audit ──────────────────────────────────────────────────────
        if (!$schema->hasTable('ud_audit')) {
            $table = $schema->createTable('ud_audit');
            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull'       => true,
            ]);
            $table->addColumn('task_id', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('group_id', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('file_path', Types::TEXT, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('file_size', Types::BIGINT, [
                'notnull' => false,
                'default' => 0,
            ]);
            $table->addColumn('action', Types::STRING, [
                'notnull' => true,
                'length'  => 32,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length'  => 64,
            ]);
            $table->addColumn('reason', Types::TEXT, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('created_at', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'ud_audit_user_id_idx');
        }

        // ── ud_hash_cache ─────────────────────────────────────────────────
        // Perceptual-hash cache so re-scans only re-hash new/changed files.
        // The Python scanner can also create this table on its own, but
        // defining it here ensures it's part of the managed schema.
        if (!$schema->hasTable('ud_hash_cache')) {
            $table = $schema->createTable('ud_hash_cache');
            $table->addColumn('fileid', Types::BIGINT, [
                'notnull' => true,
            ]);
            $table->addColumn('mtime', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('hash_algo', Types::STRING, [
                'notnull' => true,
                'length'  => 20,
            ]);
            $table->addColumn('hash_size', Types::SMALLINT, [
                'notnull' => true,
            ]);
            $table->addColumn('hash_value', Types::STRING, [
                'notnull' => true,
                'length'  => 256,
            ]);
            $table->setPrimaryKey(['fileid', 'hash_algo', 'hash_size']);
        }

        return $schema;
    }
}
