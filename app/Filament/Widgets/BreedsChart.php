<?php

namespace App\Filament\Widgets;

use App\Models\Dog;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class BreedsChart extends ChartWidget
{
    protected ?string $heading = 'Verteilung nach Rasse';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        // Top 5 Rassen holen, Rest ist oft Kleinkram
        $data = Dog::select('breed', DB::raw('count(*) as count'))
            ->whereNotNull('breed')
            ->groupBy('breed')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'breed')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Hunde',
                    'data' => array_values($data),
                    'backgroundColor' => [
                        '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7', '#d946ef',
                    ],
                    'hoverOffset' => 4,
                ],
            ],
            'labels' => array_keys($data),
        ];
    }
    protected function getType(): string
    {
        return 'doughnut';
    }
}
