<?php

namespace App\Filament\Resources\DrcParameters\Pages;

use App\Filament\Resources\DrcParameters\DrcParameterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDrcParameters extends ManageRecords
{
    protected static string $resource = DrcParameterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
