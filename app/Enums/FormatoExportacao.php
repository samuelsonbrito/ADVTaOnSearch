<?php

namespace App\Enums;

enum FormatoExportacao: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Pdf = 'pdf';

    public function rotulo(): string
    {
        return match ($this) {
            self::Csv => 'CSV',
            self::Xlsx => 'Excel (XLSX)',
            self::Pdf => 'PDF',
        };
    }

    public function mime(): string
    {
        return match ($this) {
            self::Csv => 'text/csv; charset=UTF-8',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Pdf => 'application/pdf',
        };
    }
}
