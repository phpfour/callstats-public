<?php

declare(strict_types=1);

namespace App\Support\Spreadsheet;

class CsvSafe
{
    /**
     * Characters that trigger formula evaluation when a cell value begins
     * with them in Excel / Google Sheets / LibreOffice.
     */
    private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Neutralize spreadsheet formula injection (CWE-1236) by prefixing any
     * value that starts with a formula trigger with a single quote, which
     * forces the consuming application to treat it as literal text.
     */
    public static function escape(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (in_array($value[0], self::FORMULA_TRIGGERS, true)) {
            return "'".$value;
        }

        return $value;
    }
}
