<?php

namespace Raikia\SeatMarketSeeding\Services;

use Raikia\SeatMarketSeeding\Models\SeededMarket;

class StockTargetPreviewer
{
    private StockTargetQuantity $quantities;

    public function __construct(StockTargetQuantity $quantities)
    {
        $this->quantities = $quantities;
    }

    public function preview(SeededMarket $market, array $items, string $mode, bool $keepHigherQuantity = false): array
    {
        $market->loadMissing('items');

        $existing = $market->items->keyBy('type_id');
        $rows = collect($items)->map(function (array $item) use ($existing, $mode, $keepHigherQuantity) {
            $current = $existing->get($item['type_id']);
            $currentQuantity = $current ? (int) $current->desired_quantity : 0;
            $importQuantity = (int) $item['quantity'];
            $newQuantity = $this->quantities->desiredQuantity($current, $importQuantity, $mode, $keepHigherQuantity);

            return [
                'type_id' => (int) $item['type_id'],
                'type_name' => $item['type_name'],
                'current_quantity' => $currentQuantity,
                'import_quantity' => $importQuantity,
                'new_quantity' => $newQuantity,
                'warning_quantity' => $this->quantities->warningQuantity($current, $newQuantity, $mode),
                'action' => $this->action($currentQuantity, $newQuantity, (bool) $current, $mode),
            ];
        })->values();

        return [
            'summary' => [
                'total' => $rows->count(),
                'new' => $rows->where('action', 'new')->count(),
                'increase' => $rows->where('action', 'increase')->count(),
                'reduce' => $rows->where('action', 'reduce')->count(),
                'replace' => $rows->where('action', 'replace')->count(),
                'unchanged' => $rows->where('action', 'unchanged')->count(),
            ],
            'rows' => $rows,
        ];
    }

    private function action(int $currentQuantity, int $newQuantity, bool $exists, string $mode): string
    {
        if (!$exists) {
            return 'new';
        }

        if ($currentQuantity === $newQuantity) {
            return 'unchanged';
        }

        if ($mode !== 'add') {
            return $newQuantity > $currentQuantity ? 'replace' : 'reduce';
        }

        return $newQuantity > $currentQuantity ? 'increase' : 'unchanged';
    }
}
