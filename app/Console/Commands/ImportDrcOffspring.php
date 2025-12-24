<?php

namespace App\Console\Commands;

use App\Models\Dog;
use App\Services\DrcApiService;
use Illuminate\Console\Command;

class ImportDrcOffspring extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-drc-offspring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lädt die Nachkommen und verknüpft Vater/Mutter Beziehungen';

    /**
     * Execute the console command.
     */
    public function handle(DrcApiService $api)
    {
        $this->info('Starte Verknüpfung der Stammbäume (Lineage Linking)...');

        // 1. Hole alle Hunde, die laut Basis-Daten Nachkommen haben sollten
        $parents = Dog::where('offspring_count', '>', 0)->cursor();

        $bar = $this->output->createProgressBar(count($parents));
        $bar->start();

        foreach ($parents as $parent) {
            // Nur abfragen, wenn wir Zuchtbuchnummer, Rasse und Geschlecht haben
            if (empty($parent->registration_number) || empty($parent->breed) || empty($parent->sex)) {
                $bar->advance();
                continue;
            }

            // 1. Gender mappen (API braucht 'H' oder 'R')
            $apiGender = $this->mapGenderToApi($parent->sex);

            if (!$apiGender) {
                $bar->advance();
                continue;
            }

            // 2. API Aufruf (in eigene Funktion ausgelagert)
             $payload = [
                'start' => 0,
                'limit' => 500,
                'task' => 'SUCCESSOR',
                'ZBNr' => $parent->registration_number,
                'Breed' => $parent->breed,
                'Gender' => $apiGender,
            ];
            $jsonResult = $api->fetch($payload);

            // 4. Ergebnis prüfen & Decodieren
            if (!$jsonResult) {
                $this->error("❌ Leere Antwort von Curl.");
                continue;
            }

            $dogs = is_array($jsonResult['results']) ? $jsonResult['results'] : [];
            // 3. Verarbeitung der Ergebnisse
            if (!empty($dogs)) {
                $this->processChildren($parent, $dogs);
            }

            $bar->advance();

            // Wichtig: Pause für Rate Limiting
            usleep(100000); // 0.1 Sekunden
        }
    }

    private function processChildren(Dog $parent, array $childrenData)
    {
        foreach ($childrenData as $childRow) {
            if (!isset($childRow['Rkey'])) continue;

            // Kind finden oder erstellen
            $child = Dog::firstOrCreate(
                ['drc_id' => $childRow['Rkey']],
                [
                    'name'  => html_entity_decode($childRow['Name']),
                    'raw_data' => $childRow
                ]
            );

            // Elternteil setzen
            $updated = false;

            if ($parent->sex === 'F') {
                if ($child->mother_id !== $parent->id) {
                    $child->mother_id = $parent->id;
                    $updated = true;
                }
            } elseif ($parent->sex === 'M') {
                if ($child->father_id !== $parent->id) {
                    $child->father_id = $parent->id;
                    $updated = true;
                }
            }

            if ($updated) {
                $child->save();
            }
        }
    }

    /**
     * Führt den eigentlichen Shell-Exec Curl Befehl aus.
     * * @param string $zbnr
     * @param string $breed
     * @param string $gender
     * @return string
     */
    private function fetchSuccessorsFromApi(string $zbnr, string $breed, string $gender)
    {
        // Parameter URL-Kodieren für Sicherheit und Korrektheit
        $encodedZbnr = urlencode($zbnr);
        $encodedBreed = urlencode($breed);
        // Gender ist durch mapGenderToApi schon sicher ('H' oder 'R')

        // Der Befehl wird zusammengebaut.
        // escapeshellarg() sorgt dafür, dass der Data-String keine Shell-Fehler verursacht.
        $cmd = "curl 'https://db.drc.de/adr/suche/hunde_suche_v23.php' \
                -H 'Accept: */*' \
                -H 'Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7,it;q=0.6,it-IT;q=0.5' \
                -H 'Cache-Control: no-cache' \
                -H 'Connection: keep-alive' \
                -H 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' \
                -b 'PHPSESSID=2c9qp445gf21mekc4h1st22ds0; ys-DogSearchOptionWindowId=o%3Awidth%3Dn%253A968%5Eheight%3Dn%253A724%5Ex%3Dn%253A88%5Ey%3Dn%253A50; ys-DogSearchEditorGridPanelId=o%3Acolumns%3Da%253Ao%25253Aid%25253Dn%2525253A0%25255Ewidth%25253Dn%2525253A50%255Eo%25253Aid%25253Dn%2525253A1%25255Ewidth%25253Dn%2525253A357%255Eo%25253Aid%25253Dn%2525253A2%25255Ewidth%25253Dn%2525253A70%255Eo%25253Aid%25253Dn%2525253A3%25255Ewidth%25253Dn%2525253A70%255Eo%25253Aid%25253Dn%2525253A4%25255Ewidth%25253Dn%2525253A70%255Eo%25253Aid%25253Dn%2525253A5%25255Ewidth%25253Dn%2525253A30%255Eo%25253Aid%25253Dn%2525253A6%25255Ewidth%25253Dn%2525253A30%255Eo%25253Aid%25253Dn%2525253A7%25255Ewidth%25253Dn%2525253A50%255Eo%25253Aid%25253Dn%2525253A8%25255Ewidth%25253Dn%2525253A60%255Eo%25253Aid%25253Dn%2525253A9%25255Ewidth%25253Dn%2525253A50%255Eo%25253Aid%25253Dn%2525253A10%25255Ewidth%25253Dn%2525253A50%255Eo%25253Aid%25253Dn%2525253A11%25255Ewidth%25253Dn%2525253A50%255Eo%25253Aid%25253Dn%2525253A12%25255Ewidth%25253Dn%2525253A30%255Eo%25253Aid%25253Dn%2525253A13%25255Ewidth%25253Dn%2525253A70%255Eo%25253Aid%25253Dn%2525253A14%25255Ewidth%25253Dn%2525253A70%255Eo%25253Aid%25253Dn%2525253A15%25255Ewidth%25253Dn%2525253A70%255Eo%25253Aid%25253Dn%2525253A16%25255Ewidth%25253Dn%2525253A60%255Eo%25253Aid%25253Dn%2525253A17%25255Ewidth%25253Dn%2525253A100%5Esort%3Do%253Afield%253Ds%25253AHAlleTitel%255Edirection%253Ds%25253ADESC; ys-DogSearchListingWindowId=o%3Awidth%3Dn%253A1219%5Eheight%3Dn%253A674%5Ex%3Dn%253A24%5Ey%3Dn%253A24; ys-DogSearchSearchWindowId=o%3Awidth%3Dn%253A542%5Eheight%3Dn%253A469%5Ex%3Dn%253A645%5Ey%3Dn%253A0' \
                -H 'Origin: https://db.drc.de' \
                -H 'Pragma: no-cache' \
                -H 'Referer: https://db.drc.de/adr/suche/hunde_suche_v21.html' \
                -H 'Sec-Fetch-Dest: empty' \
                -H 'Sec-Fetch-Mode: cors' \
                -H 'Sec-Fetch-Site: same-origin' \
                -H 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36' \
                -H 'X-Requested-With: XMLHttpRequest' \
                -H 'sec-ch-ua: \"Google Chrome\";v=\"137\", \"Chromium\";v=\"137\", \"Not/A)Brand\";v=\"24\"' \
                -H 'sec-ch-ua-mobile: ?0' \
                -H 'sec-ch-ua-platform: \"macOS\"' \
                --data-raw ''";

        // Ausführen und Rückgabe (Output) fangen
        // shell_exec gibt den Output als String zurück
        return shell_exec($cmd);
    }

    private function mapGenderToApi($dbGender)
    {
        $g = strtoupper(substr($dbGender, 0, 1));
        if ($g === 'H' || $g === 'F' || $g === 'W') return 'H';
        if ($g === 'R' || $g === 'M') return 'R';
        return null;
    }
}
