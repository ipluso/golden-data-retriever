<?php

namespace App\Filament\Resources\Dogs\Pages;

use App\Filament\Resources\Dogs\DogResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDog extends ViewRecord
{
    protected static string $resource = DogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
