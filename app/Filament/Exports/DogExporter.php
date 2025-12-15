<?php

namespace App\Filament\Exports;

use App\Models\Dog;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class DogExporter extends Exporter
{
    protected static ?string $model = Dog::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('drc_id'),
            ExportColumn::make('registration_number'),
            ExportColumn::make('name'),
            ExportColumn::make('breed'),
            ExportColumn::make('date_of_birth'),
            ExportColumn::make('sex'),
            ExportColumn::make('hd_score'),
            ExportColumn::make('ed_score'),
            ExportColumn::make('zw_hd'),
            ExportColumn::make('zw_ed'),
            ExportColumn::make('zw_hc'),
            ExportColumn::make('offspring_count'),
            ExportColumn::make('genetic_tests'),
            ExportColumn::make('eye_exams'),
            ExportColumn::make('orthopedic_details'),
            ExportColumn::make('work_exams'),
            ExportColumn::make('deleted_at'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your dog export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
