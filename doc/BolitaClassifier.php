<?php
class BolitaClassifier
{
    public function execute(array $input): array
    {
        $hundreds = str_pad((string)$input['centena'], 1, '0', STR_PAD_LEFT);
        $fixed = str_pad((string)$input['fijo'], 2, '0', STR_PAD_LEFT);
        $runner1 = str_pad((string)$input['corrido1'], 2, '0', STR_PAD_LEFT);
        $runner2 = str_pad((string)$input['corrido2'], 2, '0', STR_PAD_LEFT);

        $fullHundreds = $hundreds . $fixed;
        $runners = [$runner1, $runner2];
        $doublePairs = ['00','11','22','33','44','55','66','77','88','99'];

        $lines = preg_split('/\r\n|\n|\r/', $input['text']);
        $results = [];
        $summary = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Terminals: T4-10 or T-4-10
            if (preg_match('/^T-?(\d+)-(\d+)/i', $line, $matches)) {
                $number = $matches[1];
                $amount = (int)$matches[2];
                $isWinner = str_ends_with($fullHundreds, $number);
                if ($isWinner) {
                    $results[] = [
                        'type' => 'terminal',
                        'number' => $number,
                        'amount' => $amount,
                        'isWinner' => true
                    ];
                    $summary['terminal'] = ($summary['terminal'] ?? 0) + $amount;
                }
            }

            // Double pairs: P-30
            elseif (preg_match('/^P-?(\d+)/i', $line, $matches)) {
                $amount = (int)$matches[1];
                $isWinner = in_array($fixed, $doublePairs, true);
                if ($isWinner) {
                    $results[] = [
                        'type' => 'double_pair',
                        'number' => $fixed,
                        'amount' => $amount,
                        'isWinner' => true
                    ];
                    $summary['double_pair'] = ($summary['double_pair'] ?? 0) + $amount;
                }
            }

            // Parlet: 23x45-45
            elseif (preg_match('/^(\d{1,2})x(\d{1,2})-(\d+)/', $line, $matches)) {
                $a = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $b = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $amount = (int)$matches[3];
                $isWinner = $a === $fixed && in_array($b, $runners, true);
                if ($isWinner) {
                    $results[] = [
                        'type' => 'parlet',
                        'number' => "$a x $b",
                        'amount' => $amount,
                        'isWinner' => true
                    ];
                    $summary['parlet'] = ($summary['parlet'] ?? 0) + $amount;
                }
            }

            // Fixed matches
            // Hundred matches (centena + fijo)
            elseif (preg_match('/^(\d{1,3})\s*-\s*(\d+)/', $line, $matches)) {
                $number = $matches[1];
                $amount = (int)$matches[2];

                if ((int)$number === (int)$fixed) {
                    $results[] = [
                        'type' => 'fixed',
                        'number' => $number,
                        'amount' => $amount,
                        'isWinner' => true
                    ];
                    $summary['fixed'] = ($summary['fixed'] ?? 0) + $amount;
                } elseif ((int)$number === (int)$fullHundreds) {
                    $results[] = [
                        'type' => 'hundred',
                        'number' => $number,
                        'amount' => $amount,
                        'isWinner' => true
                    ];
                    $summary['hundred'] = ($summary['hundred'] ?? 0) + $amount;
                }
            }
        }

        return [
            'results' => $results,
            'summary' => $summary
        ];
    }
}
