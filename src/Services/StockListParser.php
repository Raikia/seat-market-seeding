<?php

namespace Raikia\SeatMarketSeeding\Services;

use Seat\Eveapi\Models\Sde\InvType;

class StockListParser
{
    public function parse(string $input, int $multiplier = 1): array
    {
        $items = [];

        foreach (preg_split('/\r\n|\r|\n/', $input) as $line) {
            $line = trim($line);

            if ($line === '' || $this->isIgnoredLine($line)) {
                continue;
            }

            [$name, $quantity] = $this->parseLine($line);
            $type = $this->resolveType($name);

            if (!$type) {
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

        return array_values($items);
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
