<?php

namespace Raikia\SeatMarketSeeding\Services;

use Seat\Eveapi\Models\Sde\InvType;

class StockListParser
{
    public function parse(string $input, int $multiplier = 1): array
    {
        return $this->parseWithReport($input, $multiplier)['items'];
    }

    public function parseWithReport(string $input, int $multiplier = 1): array
    {
        $items = [];
        $skipped = [];
        $ignored = 0;
        $processed = 0;

        foreach (preg_split('/\r\n|\r|\n/', $input) as $lineNumber => $line) {
            $line = trim($line);

            if ($line === '' || $this->isIgnoredLine($line)) {
                $ignored++;
                continue;
            }

            $processed++;
            [$name, $quantity] = $this->parseLine($line);

            if ($name === '') {
                $skipped[] = [
                    'line' => $line,
                    'line_number' => $lineNumber + 1,
                    'reason' => 'No item name was found.',
                ];
                continue;
            }

            $type = $this->resolveType($name);

            if (!$type) {
                $skipped[] = [
                    'line' => $line,
                    'line_number' => $lineNumber + 1,
                    'reason' => 'No published market item matched "' . $name . '".',
                ];
                continue;
            }

            $typeId = (int) $type->typeID;

            if (!isset($items[$typeId])) {
                $items[$typeId] = [
                    'type_id' => $typeId,
                    'type_name' => $type->typeName,
                    'quantity' => 0,
                ];
            }

            $items[$typeId]['quantity'] += max(1, $quantity) * max(1, $multiplier);
        }

        return [
            'items' => array_values($items),
            'validation' => [
                'processed_lines' => $processed,
                'ignored_lines' => $ignored,
                'valid_lines' => count($items),
                'skipped_lines' => count($skipped),
                'skipped' => $skipped,
            ],
        ];
    }

    private function isIgnoredLine(string $line): bool
    {
        if (preg_match('/^\[.+,\s*.+\]$/', $line)) {
            return true;
        }

        return in_array(strtolower($line), [
            '[empty high slot]',
            '[empty med slot]',
            '[empty low slot]',
            '[empty rig slot]',
            '[empty subsystem slot]',
            '[empty drone bay]',
        ], true);
    }

    private function parseLine(string $line): array
    {
        $line = preg_replace('/\s+/', ' ', $line);

        if (preg_match('/^(.+?)\s+x\s*(\d+)$/i', $line, $matches)) {
            return [trim($matches[1]), (int) $matches[2]];
        }

        if (preg_match('/^(\d+)\s*x\s+(.+)$/i', $line, $matches)) {
            return [trim($matches[2]), (int) $matches[1]];
        }

        if (preg_match('/^(.+?)\s+(\d+)$/', $line, $matches)) {
            return [trim($matches[1]), (int) $matches[2]];
        }

        return [$line, 1];
    }

    private function resolveType(string $name): ?InvType
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return InvType::where('typeName', $name)
            ->where('published', true)
            ->first();
    }
}
