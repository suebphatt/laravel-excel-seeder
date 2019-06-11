<?php


namespace bfinlay\SpreadsheetSeeder;

/**
 * Consider enhancing with FlySystem integration
 */
class FileIterator extends \AppendIterator
{
    private $count;

    public function __construct($globs, $extension = "csv", $flags = \FilesystemIterator::KEY_AS_FILENAME)
    {
        parent::__construct();

        if (! is_array($globs)) {
            $globs = [$globs];
        }

        foreach ($globs as $glob) {
            if (is_dir($glob)) {
                $glob = dirname($glob) . "/*." . $extension;
            }

            $it = new \GlobIterator(base_path() . $glob, $flags);
            $this->append($it);
            $this->count += $it->count();
        }
    }

    public function count() {
        return $this->count;
    }
}
