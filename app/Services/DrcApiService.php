<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DrcApiService
{
    const API_URL = 'https://db.drc.de/adr/suche/hunde_suche_v23.php';

    /**
     * Führt einen Request gegen die DRC API aus.
     *
     * @param array $params Die POST-Parameter (z.B. ['task' => 'SEARCH', ...])
     * @return array Die Ergebnis-Zeilen (rows)
     */
    public function fetch(array $params): array
    {
        // Standard-Parameter, die immer dabei sein sollten (Fallback)
        $defaults = [
            'start' => 0,
            'limit' => 500,
            'task'  => 'SEARCH',
            // Leere Filter mitschicken, damit die API nicht meckert
            'Name' => '', 'ZBNr' => '', 'Breed' => '', 'Gender' => '',
            'Color' => '', 'MaxAge' => '', 'MinAge' => '', 'ZZL' => '',
            'ZWHD' => '', 'ZWED' => '', 'ZWHC' => '', 'MinNK' => '',
            'Cond' => 'false', 'Chip' => '', 'Birthyear' => ''
        ];

        // Merge: Deine spezifischen Params überschreiben die Defaults
        $payload = array_merge($defaults, $params);

        // Wir bauen den --data-raw String manuell zusammen,
        // damit wir volle Kontrolle über das Encoding haben.
        $dataRawParts = [];
        foreach ($payload as $key => $value) {
            // Wichtig: urlencode für Werte, damit Leerzeichen etc. passen
            $dataRawParts[] = $key . '=' . urlencode($value);
        }
        $dataRawString = implode('&', $dataRawParts);

        // Der CURL Befehl
        // --silent unterdrückt Curls eigenen Output
        // Wir nutzen single quotes für den data-string, damit die Shell nicht interpretiert
        $command = "curl '" . self::API_URL . "' \
          -H 'Accept: */*' \
          -H 'Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7' \
          -H 'Cache-Control: no-cache' \
          -H 'Connection: keep-alive' \
          -H 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' \
          -H 'Origin: https://db.drc.de' \
          -H 'Pragma: no-cache' \
          -H 'Referer: https://db.drc.de/adr/suche/hunde_suche_v21.html' \
          -H 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36' \
          -H 'X-Requested-With: XMLHttpRequest' \
          --data-raw '{$dataRawString}' \
          --silent";

        try {
            $jsonOutput = shell_exec($command);

            if (!$jsonOutput) {
                return [];
            }

            $data = json_decode($jsonOutput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning("JSON Decode Fehler: " . json_last_error_msg());
                return [];
            }

            // Normalisierung: Manchmal 'rows', manchmal direkt Array
            return $data['rows'] ?? $data ?? [];

        } catch (\Exception $e) {
            Log::error("DRC API Fehler: " . $e->getMessage());
            return [];
        }
    }
}
