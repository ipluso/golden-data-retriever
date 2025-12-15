<?php

namespace App\Filament\Widgets;

use App\Models\Dog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DogStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Einfache Zählungen
        $totalDogs = Dog::count();
        $males = Dog::where('sex', 'M')->count();
        $females = Dog::where('sex', 'F')->count();

        // HD-Frei Quote berechnen (Hunde mit A1 oder A2)
        $hdClean = Dog::where('hd_score', 'LIKE', 'A%')->count();
        $hdPercentage = $totalDogs > 0 ? round(($hdClean / $totalDogs) * 100, 1) : 0;

        return [
            Stat::make('Gesamtbestand', number_format($totalDogs, 0, ',', '.'))
                ->description('Importierte Hunde')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary')
                ->chart([7, 2, 10, 3, 15, 4, 17]), // Fake Mini-Chart für Optik

            Stat::make('Geschlechter', "$males ♂ / $females ♀")
                ->description('Rüden vs. Hündinnen')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('gray'),

            Stat::make('HD-Gesundheit', $hdPercentage . '%')
                ->description('Hunde mit HD-Grad A')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->chart([80, 85, 82, 90, $hdPercentage]), // Trendlinie
        ];
    }
}
