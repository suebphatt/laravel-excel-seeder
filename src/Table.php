<?php


namespace bfinlay\SpreadsheetSeeder;

use DB;
use Doctrine\DBAL\Schema\Column;

class Table
{
    public $name;
    public $exists = FALSE;

    /**
     * @var string[]
     */
    private $columns;

    /**
     * 
     * See methods in vendor/doctrine/dbal/lib/Doctrine/DBAL/Schema/Column.php
     * 
     * @var Column
     */
    private $doctrineColumns;

    public function __construct($name)
    {
        $this->name = $name;
        $this->exists = $this->exists();
    }

    private function exists() {
        if( DB::getSchemaBuilder()->hasTable( $this->name ) ) return TRUE;

        return FALSE;
    }

    public function truncate( $foreignKeys = TRUE ) {
        if( ! $foreignKeys ) DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        DB::table( $this->name )->truncate();

        if( ! $foreignKeys ) DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    private function loadColumns() {
        if (! isset($this->columns)) {
            $this->columns = DB::getSchemaBuilder()->getColumnListing( $this->name );
            $this->doctrineColumns = DB::getSchemaBuilder()->getConnection()->getDoctrineSchemaManager()->listTableColumns($this->name);
        }
    }

    public function getColumns() {
        $this->loadColumns();

        return $this->columns;
    }

    public function getColumnType($name) {
        $this->loadColumns();

        return $this->doctrineColumns[$name]->getType()->getName();
    }

    public function columnExists($columnName) {
        $this->loadColumns();

        return in_array($columnName, $this->columns);
    }

    public function insertRows($rows) {
        if( empty($rows) ) return;

        DB::table( $this->name )->insert( $rows );
    }

    public function defaultValue($column, $timestamps = FALSE) {
        $this->loadColumns();

        $c = $this->doctrineColumns[$column];

        // return default value for column if set
        if ($c->getDefault()) return $c->getDefault();

        // if column is auto-incrementing return null and let database set the value
        if ($c->getAutoincrement()) return null;

        // if column accepts null values, return null
        if (! $c->getNotnull()) return null;

        // if column is numeric, return 0
        $doctrineNumericValues = ['smallint', 'integer', 'bigint', 'decimal', 'float'];
        if (in_array($c->getType()->getName(), $doctrineNumericValues)) return 0;
        
        // if column is date or time type return 
        $doctrineDateValues = ['date', 'date_immutable', 'datetime', 'datetime_immutable', 'datetimez', 'datetimez_immutable', 'time', 'time_immutable', 'dateinterval'];
        if (in_array($c->getType()->getName(), $doctrineDateValues)) {
            if ($timestamps) return date('Y-m-d H:i:s');
            else return 0;
        } 
            
        // if column is boolean return false
        if ($c->getType()->getName() == "boolean") return false;
        
        // else return empty string
        return "";
    }
}