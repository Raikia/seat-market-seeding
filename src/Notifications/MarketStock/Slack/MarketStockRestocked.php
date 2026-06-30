<?php

namespace Raikia\SeatMarketSeeding\Notifications\MarketStock\Slack;

use Illuminate\Notifications\Messages\SlackMessage;
use Raikia\SeatMarketSeeding\Support\MarketStockNotificationFormatter;
use Seat\Notifications\Notifications\AbstractSlackNotification;

class MarketStockRestocked extends AbstractSlackNotification
{
    private array $alert;

    public function __construct(array $alert)
    {
        $this->alert = $alert;
    }

    protected function populateMessage(SlackMessage $message, $notifiable)
    {
        $title = sprintf('%s market item%s restocked', number_format($this->alert['item_count']), $this->alert['item_count'] === 1 ? '' : 's');

        $message
            ->content($title)
            ->from('SeAT Market Seeding')
            ->attachment(function ($attachment) use ($title) {
                $attachment
                    ->title($title, $this->alert['dashboard_url'])
                    ->fields([
                        'Market' => sprintf('%s (%s)', $this->alert['market_name'], $this->alert['location_name']),
                        'Restocked Items' => $this->formatItems(),
                    ])
                    ->color('good');
            });

        $message->success();
    }

    private function formatItems(): string
    {
        return implode("\n", MarketStockNotificationFormatter::itemLines(
            $this->alert['items'],
            15,
            fn (array $item) => MarketStockNotificationFormatter::restockedItemLine($item)
        ));
    }
}
