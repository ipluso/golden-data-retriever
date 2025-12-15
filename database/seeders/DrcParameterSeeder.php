<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DrcParameter;
use Illuminate\Support\Facades\Storage;

class DrcParameterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Tabelle leeren, damit wir keine Duplikate bekommen
        DrcParameter::truncate();

        // 2. Datei prüfen
        if (!Storage::exists('drc_params.csv')) {
            $this->command->error('❌ Datei "drc_params.csv" nicht in "storage/app/private/" gefunden!');
            $this->command->info('Bitte exportiere deine Excel-Liste als CSV (Trennzeichen: Semikolon) und lade sie hoch.');
            return;
        }

        $this->command->info('📖 Lese CSV-Datei ein...');

        $content = Storage::get('drc_params.csv');
        $lines = explode("\n", $content);

        // Header-Zeile (Parameter;Reiter;Beschreibung;Wert) entfernen
        array_shift($lines);

        $count = 0;

        foreach ($lines as $line) {
            // Leere Zeilen überspringen
            if (empty(trim($line))) continue;

            // 3. CSV Parsen
            $data = str_getcsv($line, ';');

            // Fallback: Falls Semikolon nicht klappt, versuche Komma
            if (count($data) < 4) {
                $data = str_getcsv($line, ',');
            }

            // Wenn immer noch keine 4 Spalten da sind, überspringen
            if (count($data) < 4) continue;

            $key = trim($data[0]);          // Spalte A: Parameter (CondOA_1)
            $reiter = trim($data[1]);       // Spalte B: Reiter (Auflagen)
            $beschreibung = trim($data[2]); // Spalte C: Beschreibung (ohne Auflagen)
            $wert = trim($data[3]);         // Spalte D: Wert (HD) -> Wird unser Label

            if($reiter === '' || $reiter === 'empty') continue;

            // 4. Mapping: Welcher Reiter gehört in welche JSON-Spalte?
            $targetColumn = match($reiter) {
                'Gentests-1', 'Gentests-2' => 'genetic_tests',
                'Augen' => 'eye_exams',
                'Auflagen' => 'orthopedic_details',
                'Prüfungen/Titel' => 'work_exams',
                default => 'work_exams' // Fallback für Unbekanntes
            };

            // 5. In Datenbank speichern
            DrcParameter::create([
                'param_key' => $key,
                'category' => $reiter,
                'description' => $beschreibung,
                'label' => $wert, // Hier speichern wir "HD", "ED" etc.
                'target_column' => $targetColumn,
            ]);

            $count++;
        }

        $this->command->info("✅ Erfolgreich $count Parameter importiert!");
    }
}
