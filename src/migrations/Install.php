<?php
/**
 * Stripe Checkout plugin for Craft CMS 3.x
 *
 * Bringing the power of Stripe Checkout to your Craft templates.
 *
 * @link      https://github.com/jalendport/craft-stripecheckout
 * @copyright Copyright (c) 2018 Jalen Davenport
 */

namespace convergine\sharebox\migrations;


use Craft;
use craft\config\DbConfig;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

class Install extends Migration
{
    // Public Properties
    // =========================================================================

    public $driver;

    // Public Methods
    // =========================================================================

    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
	    if ($this->createFoldersTable()) {

		    $this->createIndex(null, '{{%conv_folders}}', 'userId', false);
		    $this->createIndex(null, '{{%conv_folders}}', 'parent_id', false);

		    $this->addForeignKey(null, '{{%conv_folders}}', ['userId'], '{{%users}}', ['id'], 'SET NULL');
		    
	    }
        if ($this->createFilesTable()) {

	        $this->createIndex(null, '{{%conv_files}}', 'userId', false);
	        $this->createIndex(null, '{{%conv_files}}', 'folder_id', false);

	        $this->addForeignKey(null, '{{%conv_files}}', ['userId'], '{{%users}}', ['id'], 'SET NULL');

        }
		if($this->createStatisticTable()){
			$this->createIndex(null, '{{%conv_stat}}', 'id_file', false);

		}

		// Refresh the db schema caches
	    Craft::$app->db->schema->refresh();
        return true;
    }

    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->dropForeignKeys();
        $this->dropTables();

        return true;
    }

    // Protected Methods
    // =========================================================================
	protected function createStatisticTable()
	{
		$tablesCreated = false;

		// statistic table
		$tableSchema = Craft::$app->db->schema->getTableSchema('{{%conv_stat}}');
		if ($tableSchema === null) {
			$tablesCreated = true;

			$this->createTable(
				'{{%conv_stat}}',
				[
					'id'          => $this->primaryKey(),
					'action'      => $this->string( 255 ),
					'ip'          => $this->string( 255 ),
					'details'     => $this->text(),
					'id_file'     => $this->integer(),
					'dateCreated' => $this->dateTime()->notNull(),
					'dateUpdated' => $this->dateTime()->notNull(),
					'uid'         => $this->uid()

				]
			);
		}

		return $tablesCreated;
	}

	protected function createFoldersTable()
	{
		$tablesCreated = false;

		// folders table
		$tableSchema = Craft::$app->db->schema->getTableSchema('{{%conv_folders}}');
		if ($tableSchema === null) {
			$tablesCreated = true;

			$this->createTable(
				'{{%conv_folders}}',
				[
					'id'          => $this->primaryKey(),
					'parent_id'   => $this->integer( 11 ),
					'userId'      => $this->integer(),
					'name'        => $this->string( 255 ),
					'dateCreated' => $this->dateTime()->notNull(),
					'dateUpdated' => $this->dateTime()->notNull(),
					'uid'         => $this->uid()

				]
			);
		}

		return $tablesCreated;
	}
    protected function createFilesTable()
    {
        $tablesCreated = false;

        // files table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%conv_files}}');
        if ($tableSchema === null) {
            $tablesCreated = true;

            $this->createTable(
                '{{%conv_files}}',
	            [
		            'id'          => $this->primaryKey(),
		            'folder_id'   => $this->integer( 11 ),
		            'userId'      => $this->integer(),
		            'name'        => $this->string( 255 ),
		            'size'        => $this->bigInteger( 20 ),
		            'path'        => $this->text(),
		            'mime'        => $this->string( 100 ),
		            'downloaded'  => $this->integer( 11 ),
		            'dateCreated' => $this->dateTime()->notNull(),
		            'dateUpdated' => $this->dateTime()->notNull(),
		            'uid'         => $this->uid()

	            ]
            );
        }

        return $tablesCreated;
    }

    protected function dropForeignKeys()
    {
		$this->dropAllForeignKeysToTable('{{%conv_files}}');
	    $this->dropAllForeignKeysToTable('{{%conv_folders}}');
    }

    protected function dropTables()
    {
        $this->dropTable('{{%conv_stat}}');
	    $this->dropTable('{{%conv_folders}}');
	    $this->dropTable('{{%conv_files}}');
    }
}
