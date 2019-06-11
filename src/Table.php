<?php


namespace bfinlay\SpreadsheetSeeder;

use DB;

class Table
{
    public $name;
    public $exists = FALSE;

    private $columns;

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
        }
    }

    public function getColumns() {
        $this->loadColumns();

        return $this->columns;
    }

    public function columnExists($columnName) {
        $this->loadColumns();

        return in_array($columnName, $this->columns);
    }

    public function insertRows($rows) {
        if( empty($rows) ) return;

        DB::table( $this->name )->insert( $rows );
    }
}