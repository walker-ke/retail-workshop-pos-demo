<?php
namespace App\Support;

class CsvCell
{
    public static function safe($value)
    {
        // Spreadsheet applications may execute text cells as formulas.
        if (is_string($value) && preg_match('/^[\s\x00-\x1f]*[=+@-]/u', $value)) return "'".$value;
        return $value;
    }
}
