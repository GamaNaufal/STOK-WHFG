<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\{Cell, DataType, DefaultValueBinder};
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;

/** Preserve report text and identifiers; quantities supplied as numbers stay numeric. */
abstract class TextSafeExport extends DefaultValueBinder implements WithCustomValueBinder
{
    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit(StringHelper::sanitizeUTF8($value), DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function download(string $filename)
    {
        $previousBinder = Cell::getValueBinder();
        try {
            return Excel::download($this, $filename);
        } finally {
            // PhpSpreadsheet's binder is process-global, including after failed exports.
            Cell::setValueBinder($previousBinder);
        }
    }
}
