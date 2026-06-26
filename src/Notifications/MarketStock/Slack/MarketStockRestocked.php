<?php

namespace Raikia\SeatMarketSeeding\Notifications\MarketStock\Slack;

use Illuminate\Notifications\Messages\SlackMessage;
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
        $items = collect($this->alert['items']);
        $lines = $items->take(15)->map(function (array $item) {
            return sprintf(
                '%s: %s / %s (was %s)',
                $item['type_name'],
                number_format($item['current_quantity']),
                number_format($item['desired_quantity']),
                $item['previous_status']
            );
        });

        if ($items->count() > $lines->count()) {
            $lines->push(sprintf('...and %s more', number_format($items->count() - $lines->count())));
        }

        return $lines->implode("\n");
    }
}
