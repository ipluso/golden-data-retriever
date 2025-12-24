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
        $defaults = [
            'task' => 'SEARCH',
            'limit' => 200000,
            'start' => 0,
        ];

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
          --data-raw '{$dataRawString}' \
          --silent";

        try {
            $res = shell_exec($command);

            if (!$res) {
                return [];
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning("JSON Decode Fehler: " . json_last_error_msg());
                return [];
            }

            // Wir entfernen Leerzeichen am Anfang/Ende
            $res = trim($res);

            // Wenn der String mit '(' beginnt und mit ')' endet, entfernen wir diese
            if (str_starts_with($res, '(') && str_ends_with($res, ')')) {
                // substr(string, start, length). -1 bei length schneidet das letzte Zeichen ab.
                $res = substr($res, 1, -1);
            }

            // Sicherheitshalber nochmal trimmen (falls Leerzeichen IN den Klammern waren)
            $res = trim($res);

            $json = json_decode($res, true);

            return $json;

        } catch (\Exception $e) {
            Log::error("DRC API Fehler: " . $e->getMessage());
            return [];
        }
    }
}
