# Laravel Spreadsheet Seeder
> #### Seed your database using CSV files, XLSX files, and more with Laravel

With this package you can save time seeding your database. Instead of typing out seeder files, you can use CSV, XLSX, or any supported spreadsheet file format to load your project's database. There are configuration options available to control the insertion of data from your spreadsheet files.

This project was forked from [laravel-csv-seeder](https://github.com/jeroenzwart/laravel-csv-seeder) and rewritten to support processing multiple input files and to use the [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) library to support XLSX and other file formats.

### Features

- Support CSV, XLS, XLSX, ODF, Gnumeric, XML, HTML, SLK files through [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) library
- Seed from multiple spreadsheet files per Laravel seeder class
- Automatically resolve CSV filename to table name.
- Automatically resolve XLSX worksheet tabs to table names.
- Automatically map CSV amd XLSX headers to table column names.
- Automatically determine delimiter for CSV files, including comma `,`, tab `\t`, pipe `|`, and semi-colon `;`
- Skip seeding data columns by using a prefix character in the spreadsheet column header.
- Hash values with a given array of column names.
- Seed default values into table columns.
- Adjust Laravel's timestamp at seeding.

## Installation
- Require this package directly by `composer require --dev bfinlay/laravel-spreadsheet-seeder`
- Or add this package in your composer.json and run `composer update`

    ```
    "bfinlay/laravel-spreadsheet-seeder": "~2.0"
    ```

## Basic usage
Extend your seed classes with `bfinlay\SpreadsheetSeeder\SpreadsheetSeeder` and set the variable `$this->file` with the paths of the spreadsheet files.  This can be a string or array of strings, and accepts wildcards.  Tablename is not required if the filename or worksheet tab of the spreadsheet is the same as the tablename. Lastly, call `parent::run()` to seed. A seed class will look like this;
```php
use bfinlay\SpreadsheetSeeder\SpreadsheetSeeder;

class UsersTableSeeder extends SpreadsheetSeeder
{
    public function __construct()
    {
        $this->file = '/database/seeds/csvs/*.csv'; // specify relative to Laravel project base path
    }
    
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Recommended when importing larger CSVs
	    DB::disableQueryLog();
	    parent::run();
    }
}
```
Place your spreadsheets into the path */database/seeds/csvs/* of your Laravel project or whatever path you specify in the constructor. As default the given spreadsheet requires a header row with names that match the columns names of the table in your database.  

A CSV example:
```
    first_name,last_name,birthday
    Foo,Bar,1970-01-01
    John,Doe,1980-01-01
```

## Configuration
- `file` *(string*) or *(array []*) - list of files or directories to process.   Can include wildcards.
- `extension` *(string 'csv'*) - the default extension used when a directory is speficied in `file`
- `tablename` *(string*) - Name of table to insert data.
- `truncate` *(boolean TRUE)*  - Truncate the table before seeding.
- `header` *(boolean TRUE)* - CSV has a header row, set FALSE if not.
- `mapping` *(array [])* - Associative array of column names in order as CSV, if empy the first row of CSV will be used as header.
- `aliases` *(array [])* - Associative array of CSV header names and column names; csvColumnName => aliasColumnName.
- `skipper` *(string %)* - Skip a CSV header and data to import in the table.
- `validate` *(array [])* - Validate a CSV row with Laravel Validation.
- `hashable` *(array ['password'])* - Array of column names to hash there values. It uses Hash::make().
- `defaults` *(array [])* - Array of table columns and its values to seed with CSV file.
- `timestamps` *(string/boolean TRUE)* - Set Laravel's timestamp in the database while seeding; set as TRUE will use current time.
- `delimiter` *(string NULL)* - The delimiter used in the CSV files.  Automatically determined by library, but can be overriden with this setting.
- `chunk` *(integer 50)* - Insert the data of rows every `chunk` while reading the CSV. _Note: the PhpSpreadsheet library loads the entire spreadsheet file into memory.  See issue #1_


## Examples
#### Table with given timestamps
Give the seeder a specific table name instead of using the CSV filename;
```php
	public function __construct()
    	{
		$this->file = '/database/seeds/csvs/users.csv';
		$this->tablename = 'email_users';
		$this->timestamps = '1970-01-01 00:00:00';
	}
```

#### Mapping
Map the CSV headers to table columns, with the following CSV;

    1,Foo,Bar
    2,John,Doe

Handle like this;    
```php
	public function __construct()
	{
		$this->file = '/database/seeds/csvs/users.csv';
		$this->mapping = ['id', 'firstname', 'lastname'];
		$this->header = FALSE;
	}
```

#### Aliases with defaults
Seed a table with aliases and default values, like this;
```php
	public function __construct()
	{
		$this->file = '/database/seeds/csvs/users.csv';
		$this->aliases = ['csvColumnName' => 'table_column_name', 'foo' => 'bar'];
		$this->defaults = ['created_by' => 'seeder', 'updated_by' => 'seeder'];
	}
```

#### Skipper
Skip a column in a CSV with a prefix. For example you use `id` in your CSV and only usable in your CSV editor. The following CSV file looks like so;

    %id,first_name,last_name,%id_copy,birthday
    1,Foo,Bar,1,1970-01-01
    2,John,Doe,2,1980-01-01

The first and fourth value of each row will be skipped with seeding. The default prefix is '%' and changeable to;
```php
	public function __construct()
	{
		$this->file = '/database/seeds/csvs/users.csv';
		$this->skipper = 'custom_';
	}
```

#### Validate
Validate each row of a CSV like this;
```php
	public function __construct()
	{
		$this->file = '/database/seeds/csvs/users.csv';
		$this->validate = [ 'name'              => 'required',
                            'email'             => 'email',
                            'email_verified_at' => 'date_format:Y-m-d H:i:s',
                            'password'          => ['required', Rule::notIn([' '])]];
	}
```

#### Hash
Hash values when seeding a CSV like this;
```php
	public function __construct()
	{
		$this->file = '/database/seeds/csvs/users.csv';
		$this->hashable = ['password', 'salt'];
	}
```

## License
Laravel CSV Seeder is open-sourced software licensed under the MIT license.
