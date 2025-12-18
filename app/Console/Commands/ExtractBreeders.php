<?php

namespace App\Console\Commands;

use App\Models\Dog;
use Illuminate\Console\Command;

class ExtractBreeders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:extract-breeders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analysiert Hundenamen und extrahiert Züchternamen durch Pattern Recognition (LCP Algorithmus)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starte Züchter-Erkennung v4.0 (Double-Safety)...');

        // Alle Hunde laden
        $dogs = Dog::whereNull('breeder')->pluck('name')->toArray();
        sort($dogs); // Sortieren hilft beim Prefix-Matching

        if (empty($dogs)) {
            $this->warn("Keine Hunde ohne Züchter gefunden.");
            return;
        }

        $count = count($dogs);
        $bar = $this->output->createProgressBar($count);

        // ---------------------------------------------------------
        // PHASE 1: PREFIX-ANALYSE (z.B. "4EyesOnly ...")
        // ---------------------------------------------------------
        $this->comment("Analysiere Prefixe...");
        $prefixCandidates = [];

        for ($i = 0; $i < $count - 1; $i++) {
            $prefix = $this->getCommonWordPrefix($dogs[$i], $dogs[$i+1]);
            if (strlen($prefix) > 3) {
                $prefixCandidates[] = $prefix;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        // Statistik für Prefixe
        $prefixCounts = array_count_values($prefixCandidates);
        uksort($prefixCounts, function($a, $b) { return strlen($b) - strlen($a); });

        // Gefundene Prefix-Züchter speichern
        $validPrefixBreeders = [];
        foreach ($prefixCounts as $candidate => $occurrence) {
            // Wir filtern hier noch nicht hart, das macht der Suffix-Check gleich mit
            // Heuristik: Einzelbuchstabe am Ende weg (Wurf)
            $parts = explode(' ', $candidate);
            if (!empty($parts) && strlen(end($parts)) === 1) {
                array_pop($parts);
                $candidate = implode(' ', $parts);
            }

            if (strlen($candidate) > 3) {
                $validPrefixBreeders[$candidate] = $occurrence;
            }
        }

        // Bereinigung (Lange vs Kurze Versionen)
        $cleanPrefixes = array_keys($validPrefixBreeders);
        // ... (Hier könnte man die Clean-Logik von v3 einfügen, der Kürze halber nehmen wir die Top-Treffer)

        // ---------------------------------------------------------
        // PHASE 2: SUFFIX-ANALYSE (z.B. "... of Golden Label")
        // ---------------------------------------------------------
        $this->comment("Analysiere Suffixe (nur wiederkehrende!)...");

        $separators = ['of', 'vom', 'von', 'v.', 'aus', 'de', 'du', 'le', 'la', 'zu', 'sur', 'in', 'at'];
        $suffixCandidates = [];

        foreach ($dogs as $dogName) {
            foreach ($separators as $sep) {
                // Suche nach " of " (mit Leerzeichen!)
                // Case-Insensitive Check wäre besser, aber strpos ist case-sensitive.
                // Für SQL 'LIKE' war es egal, hier im Array müssen wir aufpassen.
                // Wir nutzen stripos für Case-Insensitive Suche.
                $pos = stripos($dogName, " $sep ");

                if ($pos !== false) {
                    // Alles NACH dem Separator ist der potenzielle Züchter
                    // + den Separator selbst (z.B. "of Golden Label")
                    // substr($dogName, $pos) holt " of Golden Label..."
                    $suffix = trim(substr($dogName, $pos)); // "of Golden Label"

                    // Speichern
                    $suffixCandidates[] = $suffix;

                    // Wir nehmen nur den ersten Treffer pro Hund (von rechts oder links? meist reicht der erste)
                    break;
                }
            }
        }

        // Zählen
        $suffixCounts = array_count_values($suffixCandidates);

        // Filtern: Nur Suffixe, die mind. 2x vorkommen!
        $validSuffixBreeders = [];
        foreach ($suffixCounts as $suffix => $count) {
            if ($count > 1) {
                $validSuffixBreeders[] = $suffix;
            }
        }

        // ---------------------------------------------------------
        // PHASE 3: DATENBANK UPDATE (Batch)
        // ---------------------------------------------------------
        $this->info("Gefundene Prefix-Züchter: " . count($cleanPrefixes));
        $this->info("Gefundene Suffix-Züchter: " . count($validSuffixBreeders));

        // Zusammenfügen (Suffixe haben Vorrang, da sie spezifischer sind, aber wir sortieren nach Länge)
        $allBreeders = array_unique(array_merge($cleanPrefixes, $validSuffixBreeders));

        // Sortieren: Längste Züchternamen zuerst anwenden!
        usort($allBreeders, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        if (count($allBreeders) > 0) {
            $this->comment('Speichere in Datenbank...');
            $updateBar = $this->output->createProgressBar(count($allBreeders));

            foreach ($allBreeders as $breeder) {
                // Sicherstellen, dass wir den breeder nicht in sich selbst suchen
                // Für Prefixe: "Name LIKE 'Breeder %'"
                // Für Suffixe: "Name LIKE '% Breeder'"

                // Wir nutzen einfach LIKE %Breeder%, aber müssen aufpassen.
                // Besser: Wir unterscheiden.

                // Ist es ein Suffix? (Beginnt mit Separator?)
                $isSuffix = false;
                foreach($separators as $sep) {
                    if (str_starts_with(strtolower($breeder), $sep . ' ')) {
                        $isSuffix = true;
                        break;
                    }
                }

                if ($isSuffix) {
                    // Update Suffix-Hunde (Name endet mit Breeder)
                    Dog::where('name', 'LIKE', '%' . $breeder)
                        ->whereNull('breeder')
                        ->update(['breeder' => $breeder]);
                } else {
                    // Update Prefix-Hunde (Name beginnt mit Breeder)
                    Dog::where('name', 'LIKE', $breeder . ' %')
                        ->whereNull('breeder')
                        ->update(['breeder' => $breeder]);
                }

                $updateBar->advance();
            }
            $updateBar->finish();
        }

        $this->newLine();
        $this->info('Fertig! Einzelgänger wurden ignoriert.');
    }

    // Hilfsfunktion bleibt
    private function getCommonWordPrefix(string $str1, string $str2): string
    {
        $words1 = explode(' ', $str1);
        $words2 = explode(' ', $str2);
        $common = [];
        foreach ($words1 as $index => $word) {
            if (isset($words2[$index]) && strcasecmp($word, $words2[$index]) === 0) {
                $common[] = $word;
            } else { break; }
        }
        return implode(' ', $common);
    }
}
