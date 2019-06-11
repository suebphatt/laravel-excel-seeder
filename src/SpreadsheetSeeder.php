<?php

namespace bfinlay\SpreadsheetSeeder;

use DB;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\BaseReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SpreadsheetSeeder extends Seeder
{
    /**
     * Path of the CSV file
     *
     * @var string
     */
    public $file;

    public $extension = "csv";

    /**
     * Table name of database, if not set uses filename of CSV
     *
     * @var string
     */
    public $tablename;

    /**
     * Truncate table before seeding
     * Default: TRUE
     * 
     * @var boolean
     */
    public $truncate = TRUE;

    /**
     * If the CSV has headers, set TRUE
     * Default: TRUE
     *
     * @var boolean
     */
    public $header = TRUE;

    /**
     * The character that split the values in the CSV
     * Default: empty - autodetected by phpspreadsheet library
     * 
     * @var string
     */
    public $delimiter;

    /**
     * Array of column names used in the CSV
     * Name map the columns of the CSV to the columns in table
     * Mapping can also be used when there are headers in the CSV. The headers will be skipped.
     * Example: ['firstCsvColumn', 'secondCsvColumn']
     * 
     * @var array
     */
    public $mapping;

    /**
     * Array of columns names as value with header name as index
     * Example: ['csvColumn' => 'tableColumn', 'foo' => 'bar']
     *
     * @var array
     */
    public $aliases;

    /**
     * Array of column names to be hashed before inserting
     * Default: ['password']
     * Example: ['password', 'salt']
     *
     * @var array
     */
    public $hashable;

    /**
     * Array with Laravel Validation rules
     * Example: ['name' => 'required']
     *
     * @var array
     */
    public $validate;

    /**
     * Array with default value for column(s) in the table
     * Example: ['created_by' => 'seed', 'updated_by' => 'seed]
     *
     * @var array
     */
    public $defaults;

    /**
     * String of prefix used in CSV header, mapping or alias
     * When a CSV column name begins with the string, this column will be skipped to insert
     * Default: '%'
     * Example: CSV header '#id_copy' will be skipped with skipper set as '#'
     *
     * @var string
     */
    public $skipper;

    /**
     * Set the Laravel timestamps while seeding data
     * With TRUE the columns 'created_at' and 'updated_at' will be set with current date/time.
     * When set on FALSE, the fields will have NULL
     * Default: TRUE
     * Example: '1970-01-01 00:00:00'
     *
     * @var string
     */
    public $timestamps;

    /**
     * Number of rows to skip at the start of the CSV, excluding the header
     * Default: 0
     * 
     * @var integer
     */
    public $offset = 0;

    /**
     * Insert into SQL database in blocks of CSV data while parsing the CSV file
     * Default: 50
     * 
     * @var integer
     */
    public $chunk = 50;

    /*
     * Seed state
     */

    /**
     * @var \SplFileInfo
     */
    private $seedFile;

    /**
     * @var string
     */
    private $seedFileType;

    /**
     * @var BaseReader
     */
    private $seedReader;

    /**
     * @var Spreadsheet
     */
    private $seedSpreadsheet;

    /**
     * @var Worksheet
     */
    private $seedWorksheet;

    /**
     * @var Table
     */
    private $seedTable;

    /**
     * @var string[]
     */
    private $seedHeader;

    /**
     * @var string[][]
     */
    private $composedRows = [];

    /**
     * @var int
     */
    private $resultCount = 0;
    private $count = 0;
    private $total = 0;

    /**
     * Run the class
     *
     * @return void
     */
    public function run()
    {
        $fileIterator = new FileIterator($this->file, $this->extension, \FilesystemIterator::KEY_AS_FILENAME);
        if (!$fileIterator->count()) {
            $this->console( 'No spreadsheet file given', 'error' );
            return;
        }

        foreach ($fileIterator as $this->seedFile) {
            $this->seed();
        }
    }

    public function seed() {
        $filename = $this->seedFile->getPathname();
        $this->seedFileType = IOFactory::identify($filename);
        $this->seedReader = IOFactory::createReader($this->seedFileType);
        if ($this->seedFileType == "Csv" && !empty($this->delimiter)) {
            $this->seedReader->setDelimiter($this->delimiter);
        }
        $this->seedSpreadsheet = $this->seedReader->load($filename);

        foreach ($this->seedSpreadsheet->getWorksheetIterator() as $this->seedWorksheet) {
            $this->setupSeedTable();
            $this->setHeader();
            $this->setMapping();
            $this->composeHeader();
            $this->composeRows();
            $this->outputResults();
        }
    }

    private function setupSeedTable() {
        if (isset($this->tablename)) {
            $tableName = $this->tablename;
        }
        else if ($this->seedSpreadsheet->getSheetCount() == 1) {
            $tableName = $this->seedFile->getBasename("." . $this->seedFile->getExtension());
        }
        else {
            $tableName = $this->seedWorksheet->getTitle();
        }

        $this->seedTable = new Table($tableName);
        if (! $this->seedTable->exists) {
            $this->console( 'Table "'.$tableName.'" could not be found in database', 'error' );
            return;
        }

        if ($this->truncate) $this->seedTable->truncate();
    }

    /**
     * Set the header of the file
     *
     * @return void
     */
    private function setHeader()
    {
        $this->seedHeader = [];

        if( $this->header == FALSE ) return;

        $this->offset += 1;

        $cellIterator = $this->seedWorksheet->getRowIterator()->current()->getCellIterator();

        foreach ($cellIterator as $cell) {
            $this->seedHeader[] = $cell->getCalculatedValue();
        }

        if( count($this->seedHeader) == 1 && $this->seedFileType == "Csv") $this->console( 'Found only one column in header.  Maybe the delimiter set for the CSV is incorrect: ['.$this->seedReader->getDelimiter().']' );
    }

    /**
     * Set mapping to headers variable
     *
     * @return void
     */
    private function setMapping()
    {
        if( empty($this->mapping) ) return;

        $this->seedHeader = $this->mapping;
    }

    /**
     * Parse the header of CSV to required columns
     *
     * @return void
     */
    private function composeHeader()
    {
        if( empty($this->seedHeader) ) return $this->console( 'No spreadsheet headers were parsed' );

        $composer = new HeaderComposer($this->seedTable, $this->aliases, $this->skipper);

        $this->seedHeader = $composer->compose( $this->seedHeader );
    }

    /**
     * Parse each row of the CSV
     *
     * @return void
     */
    private function composeRows()
    {
        $composer = new RowComposer( $this->seedHeader, $this->defaults, $this->timestamps, $this->hashable, $this->validate );

        foreach($this->seedWorksheet->getRowIterator() as $row)
        {
            $this->total++;

            if( $this->offset > 0 ) {
                $this->offset --;
                continue;
            }

            if( empty($row) ) continue;

            $rowArray = [];
            foreach($row->getCellIterator() as $cell) {
                $value = $cell->getCalculatedValue();
                if ( is_null($value) ) $value = "";
                $rowArray[] = $value;
            }
            $composedRow = $composer->compose($rowArray);
            
            if( ! $composedRow ) continue;
            
            $this->composedRows[] = $composedRow;

            $this->count ++;
            $this->resultCount++;

            if( $this->count >= $this->chunk ) $this->insertRows();
        }

        $this->insertRows();
    }

    /**
     * Insert a chunk of rows in the table
     *
     * @return void
     */
    private function insertRows()
    {
        if( empty($this->composedRows) ) return;

        try 
        {
            $this->seedTable->insertRows($this->composedRows);

            $this->composedRows = [];

          /*
           * This seems like an error.  Seems like it would still work but would effectively set the chunk size to 1 after the first chunk
           * Seems like the count should be set to 0 instead
           */
//            $this->chunk ++;
            $this->count = 0;
        }
        catch (\Exception $e)
        {
            $this->console('Rows of the file "'.$this->seedFile->getFilename().'" sheet "'.$this->seedWorksheet->getTitle().'" has failed to insert in table "'.$this->seedTable->name.'": ' . $e->getMessage(), 'error' );

            die();
        }        
    }

    /**
     * Output the result of seeding
     *
     * @return void
     */
    private function outputResults()
    {
        $this->console( $this->resultCount.' of '.$this->total.' rows has been seeded in table "'.$this->seedTable->name.'"' );
        $this->total = 0;
        $this->resultCount = 0;
    }
 
    /**
     * Strip 
     *
     * @param [type] $string
     * @return string
     */
    private function stripUtf8Bom( $string )
    {
        $bom    = pack('H*', 'EFBBBF');
        $string = preg_replace("/^$bom/", '', $string);
        
        return $string;
    }
    
    /**
     * Logging
     *
     * @param string $message
     * @param string $level
     * @return void
     */
    private function console( $message, $level = FALSE )
    {
        if( $level ) $message = '<'.$level.'>'.$message.'</'.$level.'>';

        $this->command->line( '<comment>SpreadsheetSeeder: </comment>'.$message );
    }

}
