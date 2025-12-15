<?php

namespace App\Filament\Widgets;

use App\Models\Dog;
use Filament\Widgets\ChartWidget;

class HealthChart extends ChartWidget
{
    protected ?string $heading = 'Hüftgelenksdysplasie (HD) Verteilung';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        // Wir gruppieren nach dem ersten Buchstaben des HD-Scores (A, B, C...)
        // SQL-Magic für Performance:
        $data = Dog::selectRaw('LEFT(hd_score, 1) as grade, count(*) as total')
            ->whereNotNull('hd_score')
            ->where('hd_score', '!=', '')
            ->where('hd_score', '!=', '-')
            ->groupBy('grade')
            ->orderBy('grade') // A zuerst, dann B...
            ->pluck('total', 'grade')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Anzahl Hunde',
                    'data' => array_values($data),
                    'backgroundColor' => [
                        '#22c55e', // A -> Grün
                        '#84cc16', // B -> Hellgrün
                        '#eab308', // C -> Gelb
                        '#f97316', // D -> Orange
                        '#ef4444', // E -> Rot
                    ],
                ],
            ],
            'labels' => array_keys($data), // A, B, C...
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
