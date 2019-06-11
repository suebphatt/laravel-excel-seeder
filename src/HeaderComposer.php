<?php

namespace bfinlay\SpreadsheetSeeder;

use DB;

class HeaderComposer
{   
    private $aliases      = [];
    private $skipper      = '%';

    /**
     * @var Table
     */
    private $table;
    private $key;
    private $name;

    private $composedHeader = [];

    /**
     * Set the tablename
     *
     * @param string $tablename
     */
    public function __construct( $table, $aliases, $skipper )
    {
        $this->table = $table;

        $this->aliases = $aliases === NULL ? $this->aliases : $aliases;

        $this->skipper = $skipper === NULL ? $this->skipper : $skipper;
    }

    /**
     * Compose the given header for seeding
     *
     * @param array $header
     * @return array The composed header
     */
    public function compose( $header )
    {
        if( empty($this->table) or empty($header) ) return;

        foreach( $header as $this->key => $this->name )
        {
            $this->aliasColumns();

            $this->skipColumns();

            $this->checkColumns();;
        }

        return $this->composedHeader;
    }

    /**
     * Rename columns with aliases
     *
     * @return void
     */
    private function aliasColumns()
    {
        if( empty($this->aliases) ) return;
        
        if( array_key_exists($this->name, $this->aliases) )
        {
            $this->name = $this->aliases[$this->name];
            $this->composedHeader[$this->key] = $this->name;
        }
    }

    /**
     * Skip columns starting with a given string, default %
     *
     * @return void
     */
    private function skipColumns()
    {
        if( ! isset($this->skipper) ) return; 

        if( $this->skipper != substr($this->name, 0, 1) ) $this->composedHeader[$this->key] = $this->name;
    }
    
    /**
     * Check if a column exists in the table
     *
     * @return void
     */
    private function checkColumns()
    {
        if( ! $this->table->columnExists($this->name) ) unset($this->composedHeader[$this->key]);
    }

}