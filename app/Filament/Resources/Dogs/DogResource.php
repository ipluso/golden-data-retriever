<?php

namespace App\Filament\Resources\Dogs;

use App\Filament\Exports\DogExporter;
use App\Filament\Resources\Dogs\Pages\CreateDog;
use App\Filament\Resources\Dogs\Pages\EditDog;
use App\Filament\Resources\Dogs\Pages\ListDogs;
use App\Filament\Resources\Dogs\Schemas\DogForm;
use App\Filament\Resources\Dogs\Tables\DogsTable;
use App\Models\Dog;
use App\Models\DrcParameter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Group;
use UnitEnum;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class DogResource extends Resource
{
    protected static ?string $model = Dog::class;
    protected static ?string $navigationLabel = 'Hunde';
    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Deutscher Retriever Club';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Hunde-Daten')
                    ->tabs([
                        // TAB: Stammdaten (bleibt bearbeitbar)
                        Tabs\Tab::make('Stammdaten')
                            ->icon('heroicon-m-identification')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('name')->required()->columnSpan(2),
                                    TextInput::make('registration_number')->label('ZBNr')->required(),
                                    TextInput::make('drc_id')->label('DRC-ID')->disabled(),
                                    Select::make('sex')->options(['M' => 'Rüde', 'F' => 'Hündin']),
                                    DatePicker::make('date_of_birth'),
                                    TextInput::make('breeder'),
                                    TextInput::make('breed')->default('Nova-Scotia-Duck-Tolling-Retriever'),
                                ]),
                                Group::make()->schema([
                                    Select::make('father_id')
                                        ->relationship('father', 'name')
                                        ->label('Vater')
                                        ->searchable()
                                        ->preload()
                                        ->suffixAction(
                                            Action::make('view_father')
                                            ->icon('heroicon-m-arrow-top-right-on-square')
                                            ->tooltip('Zum Vater springen')
                                                ->url(function ($state) {
                                                    if (!$state) return null;
                                                    return DogResource::getUrl('edit', ['record' => $state]);
                                                })
                                                ->hidden(fn ($state) => $state === null),
                                        ),
                                    Select::make('mother_id')
                                        ->relationship('mother', 'name')
                                        ->label('Mutter')
                                        ->searchable()
                                        ->preload()
                                        ->suffixAction(
                                            Action::make('view_mother')
                                                ->icon('heroicon-m-arrow-top-right-on-square')
                                                ->tooltip('Zur Mutter springen')
                                                ->url(function ($state) {
                                                    if (!$state) return null;
                                                    return DogResource::getUrl('edit', ['record' => $state]);
                                                })
                                                ->hidden(fn ($state) => $state === null),
                                        ),
                                ])->columns(2),
                            ]),

                        // TAB: NACHKOMMEN
                        Tabs\Tab::make('Nachkommen')
                            ->icon('heroicon-m-users') // Ein passendes Icon
                            ->badge(fn ($record) => $record?->children?->count()) // Zeigt Anzahl im Tab-Titel an!
                            ->schema([
                                Placeholder::make('children_list')
                                    ->hiddenLabel() // Label ausblenden, der Tab-Titel reicht
                                    ->content(function ($record) {
                                        if (!$record || $record->children->count() === 0) {
                                            return 'Keine Nachkommen verzeichnet.';
                                        }

                                        $links = $record->children->map(function ($child) {
                                            $url = DogResource::getUrl('edit', ['record' => $child->id]);
                                            return <<<HTML
                                            <div class="mb-1">
                                                <a href="{$url}" class="font-medium text-primary-600 underline hover:text-primary-500">
                                                    {$child->name}
                                                </a>
                                                <span class="text-gray-500 text-sm">({$child->registration_number})</span>
                                            </div>
                                        HTML;
                                        })->join('');

                                        return new HtmlString($links);
                                    }),
                            ]),

                        // TAB: Klinische Werte (bleibt bearbeitbar)
                        Tabs\Tab::make('Klinische Werte')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('hd_score'), TextInput::make('zw_hd')->numeric(),
                                    TextInput::make('ed_score'), TextInput::make('zw_ed')->numeric(),
                                ]),
                            ]),

                        // TAB: GENETIK (Jetzt als Badges!)
                        Tabs\Tab::make('Genetik')
                            ->icon('heroicon-m-beaker')
                            ->schema([
                                // Hier rufen wir die neue Badge-Funktion auf
                                self::getBadgesField('genetic_tests', 'Genetische Befunde'),
                             ]),

                        // TAB: AUGEN
                        Tabs\Tab::make('Augen')
                            ->icon('heroicon-m-eye')
                            ->schema([
                                self::getBadgesField('eye_exams', 'Augenuntersuchungen'),
                            ]),

                        // TAB: SONSTIGES
                        Tabs\Tab::make('Sonstiges')
                            ->icon('heroicon-m-clipboard-document-list')
                            ->schema([
                                self::getBadgesField('orthopedic_details', 'Auflagen & Befunde', 'ortho'),
                                self::getBadgesField('work_exams', 'Prüfungen & Titel', 'work'),
                            ]),
                        // TAB: Import
                        Tabs\Tab::make('Import')
                            ->icon('heroicon-m-circle-stack')
                            ->schema([
                                Textarea::make('raw_data')
                                    ->label('') // Kein Label nötig, da Section-Header reicht
                                    ->rows(20) // Genug Platz geben
                                    ->disabled() // Nicht editierbar machen
                                    ->columnSpanFull()
                                    // WICHTIG: Das Array wieder in schönen Text umwandeln
                                    ->formatStateUsing(fn ($state) => json_encode(
                                        $state,
                                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                    ))
                                    // Optional: Code-Font für bessere Lesbarkeit
                                    ->extraAttributes(['class' => 'font-mono text-xs']),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->defaultSort('drc_id', 'asc')
        ->columns([
            TextColumn::make('name')
                ->label('Name')
                ->searchable()
                ->weight('bold')
                ->description(fn (Dog $record) => $record->breed),

            TextColumn::make('breeder')
                ->label('Züchter')
                ->sortable()
                ->searchable(),

            TextColumn::make('date_of_birth')
                ->label('Wurfdatum')
                ->sortable()
                ->date('d.m.Y')
                ->description(fn (Dog $record) => $record->date_of_birth?->age . ' Jahre'),

            TextColumn::make('registration_number')
                ->label('ZB-Nr.')
                ->searchable()->sortable()->fontFamily('mono')->copyable(),

            TextColumn::make('breed')
                ->label('Rasse')
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('sex')
                ->label('Geschlecht')
                ->badge()
                ->formatStateUsing(fn ($state) => match ($state) { 'M' => 'Rüde', 'F' => 'Hündin', default => $state })
                ->color(fn ($state) => match ($state) { 'M' => 'info', 'F' => 'danger', default => 'gray' }),

            TextColumn::make('father.name')
                ->label('Vater')
                ->limit(10)
                ->searchable()
                ->url(fn ($record) => $record->father_id
                    ? DogResource::getUrl('edit', ['record' => $record->father_id])
                    : null
                )
                ->color('primary')
                ->placeholder('-'),

            TextColumn::make('mother.name')
                ->label('Mutter')
                ->limit(10)
                ->searchable()
                ->url(fn ($record) => $record->mother_id
                    ? DogResource::getUrl('edit', ['record' => $record->mother_id])
                    : null
                )
                ->color('primary')
                ->placeholder('-'),

            TextColumn::make('hd_score')
                ->label('HD')
                ->badge()
                ->color(fn ($state) => match (substr($state, 0, 1)) { 'A' => 'success', 'B' => 'warning', default => 'danger' }),

            TextColumn::make('ed_score')
                ->label('ED')
                ->badge()
                ->color(fn ($state) => in_array($state, ['frei', 'Grenzfall']) ? 'success' : 'gray'),

            // ZUCHTWERTE
            TextColumn::make('zw_hd')
                ->label('ZW HD')->sortable()
                ->badge()
                ->toggleable()
                ->color(fn ($state) => $state < 95 ? 'success' : ($state > 105 ? 'danger' : 'warning')),

            TextColumn::make('zw_ed')
                ->label('ZW ED')->sortable()
                ->badge()
                ->toggleable()
                ->color(fn ($state) => $state < 95 ? 'success' : ($state > 105 ? 'danger' : 'warning')),

            TextColumn::make('zw_hc')
                ->label('ZW HC')->sortable()
                ->badge()
                ->toggleable()
                ->color(fn ($state) => $state < 95 ? 'success' : ($state > 105 ? 'danger' : 'warning')),

            TextColumn::make('offspring_count')
                ->label('Nachkommen')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('genetic_tests')
                ->label('Gentests')
                ->badge()
                ->separator(',')
                ->limitList(2)
                ->listWithLineBreaks()
                ->getStateUsing(fn (Dog $record) => self::resolveJsonLabels($record->genetic_tests))
                ->color(fn (string $state): string => match (true) {
                    str_contains(strtolower($state), 'frei') => 'success',      // Grün bei "frei"
                    str_contains(strtolower($state), 'träger') => 'warning',    // Orange bei "Träger"
                    str_contains(strtolower($state), 'betroffen') => 'danger',  // Rot bei "betroffen"
                    default => 'info',                                          // Blau als Standard
                })
                ->toggleable(),
            TextColumn::make('eye_exams')
                ->label('Augen')
                ->badge()->separator(',')
                ->limitList(2)
                ->listWithLineBreaks()
                ->getStateUsing(fn (Dog $record) => self::resolveJsonLabels($record->eye_exams))
                ->color('info')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('orthopedic_details')
                ->label('Auflagen')
                ->badge()->separator(',')
                ->limitList(2)
                ->listWithLineBreaks()
                ->getStateUsing(fn (Dog $record) => self::resolveJsonLabels($record->orthopedic_details))
                ->color('info')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('work_exams')
                ->label('Prüfungen')
                ->badge()->separator(',')
                ->limitList(2)
                ->listWithLineBreaks()
                ->getStateUsing(fn (Dog $record) => self::resolveJsonLabels($record->work_exams))
                ->color('info')
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([])
        ->toolbarActions([
            //ExportAction::make()
            //    ->exporter(DogExporter::class)
            //    ->formats([
            //        ExportFormat::Csv,
            //    ])
            //    ->columnMappingColumns(3),
        ])
        ->recordActions([
        ]);
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDogs::route('/'),
            'create' => Pages\CreateDog::route('/create'),
            'edit' => Pages\EditDog::route('/{record}/edit'),
        ];
    }

    private static function resolveJsonLabels(?array $json): array
    {
        if (empty($json)) return [];

        // Nur aktive Keys holen
        $activeKeys = array_keys(array_filter($json, fn($v) => $v === true));

        // Wir holen Label UND Beschreibung und verknüpfen sie
        // Ergebnis z.B.: "prcd-PRA: Träger" oder "EIC: frei"
        return DrcParameter::whereIn('param_key', $activeKeys)
            ->get()
            ->map(fn ($p) => "{$p->description}: {$p->label}")
            ->toArray();
    }

    private static function formatJsonList(?array $json): string
    {
        if (empty($json)) return '<span class="text-gray-400 text-sm">- Keine Einträge -</span>';

        $activeKeys = array_keys(array_filter($json, fn($v) => $v === true));
        $labels = DrcParameter::whereIn('param_key', $activeKeys)->get();

        if ($labels->isEmpty()) return '<span class="text-gray-400 text-sm">- Keine Einträge -</span>';

        return $labels->map(function($param) {
            // Farb-Logik für die Badges in der Liste
            $colorClass = match($param->category) {
                'Prüfungen/Titel' => 'bg-yellow-100 text-yellow-800', // Gold für Preise
                'Auflagen' => 'bg-red-50 text-red-700', // Rot für Auflagen
                default => 'bg-blue-50 text-blue-700', // Blau für Medizin
            };

            return sprintf(
                '<div class="flex items-center gap-2 mb-1.5">' .
                '<span class="text-xs font-mono px-1.5 py-0.5 rounded %s border border-opacity-20">%s</span>' .
                '<span class="font-medium text-sm">%s</span>' .
                '<span class="text-xs text-gray-500">(%s)</span>' .
                '</div>',
                $colorClass,
                $param->param_key,
                $param->label,
                $param->description
            );
        })->join('');
    }

    /**
     * Erstellt ein Read-Only Feld mit bunten Badges für das Formular.
     */
    private static function getBadgesField(string $column, string $label, string $type = 'generic'): Placeholder
    {
        return Placeholder::make($column . '_display')
            ->label($label)
            ->content(function ($record) use ($column, $type) {
                // Wenn wir im "Erstellen"-Modus sind, gibt es noch keinen Record
                if (!$record) {
                    return new HtmlString('<span class="text-gray-400 italic">Daten werden nach Speichern importiert</span>');
                }

                // Daten holen (das JSON Array/Objekt)
                $data = $record->{$column};

                // HTML generieren
                return new HtmlString(self::renderBadgesHtml($data, $type));
            });
    }

    /**
     * Generiert den HTML-Code für die Badges (mit Farblogik).
     */
    private static function renderBadgesHtml(?array $json, string $type): string
    {
        if (empty($json)) {
            return '<span class="text-gray-400 italic text-sm">- Keine Einträge -</span>';
        }

        // Keys filtern (nur true Werte)
        // Wir unterstützen hier beide Formate (Liste oder Key=>Value), zur Sicherheit
        $activeKeys = [];
        foreach ($json as $key => $value) {
            if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
                $activeKeys[] = $key;
            } elseif (is_int($key) && is_string($value)) {
                // Falls es eine flache Liste ist ['CondGT_01']
                $activeKeys[] = $value;
            }
        }

        if (empty($activeKeys)) {
            return '<span class="text-gray-400 italic text-sm">- Keine Einträge -</span>';
        }

        // Parameter aus DB laden
        $params = DrcParameter::whereIn('param_key', $activeKeys)->get();

        if ($params->isEmpty()) {
            return '<span class="text-gray-400 italic text-sm">- Keine bekannten Parameter -</span>';
        }

        $html = '';
        foreach ($params as $p) {
            $html .= '<span class="fi-color fi-color-warning fi-text-color-700 dark:fi-text-color-400 fi-badge fi-size-sm">';
            // --- FARBLOGIK (Copy & Paste aus Infolist, damit es einheitlich ist) ---
            $lowerDesc = mb_strtolower($p->description);
            $lowerLabel = mb_strtolower($p->label);

            if ($type === 'work') {
                $bgColor = 'bg-yellow-50 text-yellow-700 border-yellow-200';
            } elseif ($type === 'ortho') {
                $bgColor = 'bg-red-50 text-red-700 border-red-200';
            } else {
                // Gesundheit
                if (str_contains($lowerDesc, 'frei') || str_contains($lowerLabel, 'frei')) {
                    $bgColor = 'bg-green-50 text-green-700 border-green-200';
                } elseif (str_contains($lowerDesc, 'träger')) {
                    $bgColor = 'bg-orange-50 text-orange-700 border-orange-200';
                } elseif (str_contains($lowerDesc, 'betroffen')) {
                    $bgColor = 'bg-red-50 text-red-700 border-red-200';
                } else {
                    $bgColor = 'bg-blue-50 text-blue-700 border-blue-200';
                }
            }

            // HTML Badge
            $html .= sprintf(
                '<span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium border %s">
                <span class="font-bold mr-1">%s:</span> %s
             </span>',
                $bgColor,
                $p->description,
                $p->label
            );
            $html .= '</span>';
        }

        return $html;
    }
}
