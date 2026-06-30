<?php

namespace Raikia\SeatMarketSeeding\Notifications\MarketStock\Slack;

use Illuminate\Notifications\Messages\SlackMessage;
use Raikia\SeatMarketSeeding\Support\MarketStockNotificationFormatter;
use Seat\Notifications\Notifications\AbstractSlackNotification;

class MarketStockTransition extends AbstractSlackNotification
{
    private array $alert;

    public function __construct(array $alert)
    {
        $this->alert = $alert;
    }

    protected function populateMessage(SlackMessage $message, $notifiable)
    {
        $empty = $this->alert['current_status'] === 'empty';
        $this->normalizeItems();
        $title = sprintf(
            '%s market item%s %s',
            number_format($this->alert['item_count']),
            $this->alert['item_count'] === 1 ? '' : 's',
            $empty ? 'empty' : 'low'
        );

        $message
            ->content($title)
            ->from('SeAT Market Seeding')
            ->attachment(function ($attachment) use ($title, $empty) {
                $attachment
                    ->title($title, $this->alert['dashboard_url'])
                    ->fields([
                        'Market' => sprintf('%s (%s)', $this->alert['market_name'], $this->alert['location_name']),
                        $empty ? 'Empty Items' : 'Low Items' => $this->formatItems(),
                    ])
                    ->color($empty ? 'danger' : 'warning');
            });

        $empty ? $message->error() : $message->warning();
    }

    private function normalizeItems(): void
    {
        if (isset($this->alert['items'])) {
            return;
        }

        $this->alert['items'] = [[
            'type_name' => $this->alert['type_name'],
            'current_quantity' => $this->alert['current_quantity'],
            'warning_quantity' => $this->alert['warning_quantity'],
            'desired_quantity' => $this->alert['desired_quantity'],
            'previous_status' => $this->alert['previous_status'],
            'current_status' => $this->alert['current_status'],
        ]];
        $this->alert['item_count'] = 1;
    }

    private function formatItems(): string
    {
        return implode("\n", MarketStockNotificationFormatter::itemLines(
            $this->alert['items'],
            15,
            fn (array $item) => MarketStockNotificationFormatter::transitionItemLine($item)
        ));
    }
}
