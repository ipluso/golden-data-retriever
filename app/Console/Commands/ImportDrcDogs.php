<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Dog;
use App\Models\DrcParameter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class ImportDrcDogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-drc-dogs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importiert Hunde vom DRC basierend auf der Parameter-Tabelle';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Parameter aus der Datenbank laden
        $parameters = DrcParameter::all();

        if ($parameters->isEmpty()) {
            $this->error('Keine Parameter gefunden! Bitte führe erst den Seeder aus.');
            return;
        }

        $this->info("🚀 Starte Golden Data Retriever Pipeline (High-Performance Mode)...");

        // 2. Den Basis-Payload bauen (Exakt wie im Curl)
        // Wir setzen erstmal ALLES auf Standardwerte (leer oder false)
        $staticPayload = [
            'task' => 'SEARCH',
            'limit' => 200000,
            'start' => 0,
            'Name' => '',
            'ZBNr' => '',
            'Breed' => '',
            'Color' => '',
            'MaxAge' => '',
            'MinAge' => '',
            'Gender' => '',
            'ZZL' => '',
            'ZWHD' => '',
            'ZWED' => '',
            'ZWHC' => '',
            'MinNK' => '',
            'Cond' => 'false',
            'Chip' => '',
            'Birthyear' => '',
            'CondGT_29' => '',
            'CondSO_04' => '',
            'CondTI_01' => '',
            'CondOD_01' => '',
        ];

        // Wir fügen alle bekannten Parameter aus der DB als 'false' hinzu
        // Damit ist der Request vollständig, auch wenn wir nichts angekreuzt haben.
        $basePayload = $staticPayload;
        foreach ($parameters as $p) {
            $basePayload[$p->param_key] = 'false';
        }
        $this->info("📦 Basis-Payload erstellt (" . count($basePayload) . " Felder).");

        // Wir loopen durch jeden Parameter (z.B. "HD: ohne Auflagen")
        foreach ($parameters as $index => $param) {
            $step = $index + 1;
            $this->info("[$step/{$parameters->count()}] Scrape: {$param->description} ({$param->param_key})");

            // Payload kopieren und den EINEN aktuellen Parameter aktivieren
            $currentPayload = $basePayload;
            $currentPayload[$param->param_key] = 'true';

            $jsonResult = $this->executeCurl($currentPayload);

            // 4. Ergebnis prüfen & Decodieren
            if (!$jsonResult) {
                $this->error("❌ Leere Antwort von Curl.");
                continue;
            }

            // Wir entfernen Leerzeichen am Anfang/Ende
            $jsonResult = trim($jsonResult);

            // Wenn der String mit '(' beginnt und mit ')' endet, entfernen wir diese
            if (str_starts_with($jsonResult, '(') && str_ends_with($jsonResult, ')')) {
                // substr(string, start, length). -1 bei length schneidet das letzte Zeichen ab.
                $jsonResult = substr($jsonResult, 1, -1);
            }

            // Sicherheitshalber nochmal trimmen (falls Leerzeichen IN den Klammern waren)
            $jsonResult = trim($jsonResult);

            $dogsRaw = json_decode($jsonResult, true);
            $dogs = is_array($dogsRaw['results']) ? $dogsRaw['results'] : [];
            $count = count($dogs);

            $this->comment("   -> $count Hunde gefunden. Verarbeite...");

            // Wenn keine Hunde gefunden wurden, weiter zum nächsten Parameter
            if ($count === 0) continue;

            // 3. Verarbeitung der Hunde
            $bar = $this->output->createProgressBar($count);
            $bar->start();

            foreach ($dogs as $dogData) {
                // Rkey prüfen (Das ist jetzt unser wichtigstes Feld!)
                $rkey = isset($dogData['Rkey']) ? (int)$dogData['Rkey'] : 0;

                // Ohne Rkey können wir den Hund nicht eindeutig zuordnen -> Skip
                if ($rkey === 0) {
                    $bar->advance();
                    continue;
                }
                // 4. UpdateOrCreate mit Rkey als Identifier
                // A) Basis-Datensatz erstellen oder aktualisieren
                $dog = Dog::updateOrCreate(
                    ['drc_id' => $rkey],
                    [
                        'registration_number' =>trim($dogData['ZBNr'] ?? ''),
                        'name' => trim($dogData['Name']),
                        'breed' => $dogData['Rasse'] ?? null,
                        'date_of_birth' => $this->parseDate($dogData['Wurfdatum'] ?? null),
                        'sex' => $this->mapSex($dogData['Geschlecht'] ?? null),
                        'offspring_count' => $this->cleanNum($dogData['AnzNachkommen'] ?? null),
                        'hd_score' => $this->cleanValue($dogData['HD_Grad'] ?? null),
                        'ed_score' => $this->cleanValue($dogData['ED_rechts'] ?? null),
                        'zw_hd' => $this->parseZw($dogData['ZW_HD'] ?? null),
                        'zw_ed' => $this->parseZw($dogData['ZW_ED'] ?? null),
                        'zw_hc' => $this->parseZw($dogData['ZW_HC'] ?? null),
                        'raw_data' => $dogData,
                    ]
                );

                // B) Das dynamische Merkmal im JSON speichern
                // Wir nutzen target_column aus der DB (z.B. 'genetic_tests')
                $targetColumn = $param->target_column;

                // Aktuelles Array laden
                $currentAttributes = $dog->{$targetColumn} ?? [];

                // Key: Der technische Parameter (z.B. "CondGT_12")
                // Value: true (Boolean)
                $currentAttributes[$param->param_key] = true;

                // Speichern
                $dog->{$targetColumn} = $currentAttributes;
                $dog->save();

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        $this->info("✅ Import erfolgreich abgeschlossen!");
    }

    /**
     * Baut den Curl-Befehl als String zusammen und führt ihn in der Shell aus.
     */
    private function executeCurl(array $payload)
    {
        // Wir wandeln das Array in einen Query-String um (key=val&key2=val2)
        // Das entspricht dem --data-raw Inhalt
        $dataString = http_build_query($payload);

        // Der Befehl wird zusammengebaut.
        // escapeshellarg() sorgt dafür, dass der Data-String keine Shell-Fehler verursacht.
        $cmd = "curl 'https://db.drc.de/adr/suche/hunde_suche_v23.php' \
          -H 'Accept: */*' \
          -H 'Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7,it;q=0.6,it-IT;q=0.5' \
          -H 'Cache-Control: no-cache' \
          -H 'Connection: keep-alive' \
          -H 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' \
          -b 'PHPSESSID=k6or0ecoh66o804urvj79iaj11; ys-DogSearchEditorGridPanelId=o%3Acolumns%3Da%253Ao%25253Aid%25253Dn%2525253A0%25255Ewidth%25253Dn%2525253A50%25255Ehidden%25253Db%2525253A1%255Eo%25253Aid%25253Dn%2525253A1%25255Ewidth%25253Dn%2525253A200%255Eo%25253Aid%25253Dn%2525253A2%25255Ewidth%25253Dn%2525253A70%255Eo%25253Aid%25253Dn%2525253A3%25255Ewidth%25253Dn%2525253A70%255Eo%25253Aid%25253Dn%2525253A4%25255Ewidth%25253Dn%2525253A70%255Eo%25253Aid%25253Dn%2525253A5%25255Ewidth%25253Dn%2525253A30%255Eo%25253Aid%25253Dn%2525253A6%25255Ewidth%25253Dn%2525253A30%255Eo%25253Aid%25253Dn%2525253A7%25255Ewidth%25253Dn%2525253A50%255Eo%25253Aid%25253Dn%2525253A8%25255Ewidth%25253Dn%2525253A60%25255Ehidden%25253Db%2525253A1%255Eo%25253Aid%25253Dn%2525253A9%25255Ewidth%25253Dn%2525253A50%255Eo%25253Aid%25253Dn%2525253A10%25255Ewidth%25253Dn%2525253A50%255Eo%25253Aid%25253Dn%2525253A11%25255Ewidth%25253Dn%2525253A50%255Eo%25253Aid%25253Dn%2525253A12%25255Ewidth%25253Dn%2525253A30%255Eo%25253Aid%25253Dn%2525253A13%25255Ewidth%25253Dn%2525253A70%25255Ehidden%25253Db%2525253A1%255Eo%25253Aid%25253Dn%2525253A14%25255Ewidth%25253Dn%2525253A70%25255Ehidden%25253Db%2525253A1%255Eo%25253Aid%25253Dn%2525253A15%25255Ewidth%25253Dn%2525253A70%25255Ehidden%25253Db%2525253A1%255Eo%25253Aid%25253Dn%2525253A16%25255Ewidth%25253Dn%2525253A60%25255Ehidden%25253Db%2525253A1%255Eo%25253Aid%25253Dn%2525253A17%25255Ewidth%25253Dn%2525253A100%25255Ehidden%25253Db%2525253A1' \
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
          --data-raw ".escapeshellarg($dataString);

        // Ausführen und Rückgabe (Output) fangen
        // shell_exec gibt den Output als String zurück
        return shell_exec($cmd);
    }

    /**
     * Hilfsfunktion: Wandelt "10/30/2017" in "2017-10-30" um.
     */
    private function parseDate($dateString)
    {
        if (empty($dateString) || $dateString === '-' || strlen($dateString) < 6) return null;

        try {
            return Carbon::createFromFormat('m/d/Y', $dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            // Fallback für andere Formate oder fehlerhafte Daten
            return null;
        }
    }

    /**
     * Hilfsfunktion: Mappt H/R zu F/M (ISO Standard).
     */
    private function mapSex($germanSex)
    {
        return match ($germanSex) {
            'H' => 'F', // Hündin -> Female
            'R' => 'M', // Rüde -> Male
            default => null,
        };
    }

    /**
     * Hilfsfunktion: Bereinigt "-" und leere Strings.
     */
    private function cleanValue($value)
    {
        if (empty($value) || $value === '-' || $value === ',,') {
            return null;
        }
        return trim($value);
    }

    /**
     * Hilfsfunktion: Bereinigt "-" und leere Strings.
     */
    private function cleanNum($value)
    {
        if (empty($value) || $value === '-') {
            return 0;
        }
        return (int) $value;
    }

    /**
     * Hilfsfunktion: Zuchtwert String zu Integer.
     */
    private function parseZw($value)
    {
        if (empty($value) || $value === '-' || $value === '0') return null;
        return (int) $value;
    }
}
