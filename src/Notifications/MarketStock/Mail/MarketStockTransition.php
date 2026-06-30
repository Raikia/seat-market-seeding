<?php

namespace Raikia\SeatMarketSeeding\Notifications\MarketStock\Mail;

use Illuminate\Notifications\Messages\MailMessage;
use Raikia\SeatMarketSeeding\Support\MarketStockNotificationFormatter;
use Seat\Notifications\Notifications\AbstractMailNotification;

class MarketStockTransition extends AbstractMailNotification
{
    private array $alert;

    public function __construct(array $alert)
    {
        $this->alert = $alert;
    }

    protected function populateMessage(MailMessage $message, $notifiable)
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
            ->subject('SeAT Market Seeding: ' . $title)
            ->line(sprintf('%s on %s.', ucfirst($empty ? 'empty stock' : 'low stock'), $this->alert['market_name']))
            ->line(sprintf('Location: %s', $this->alert['location_name']));

        foreach (MarketStockNotificationFormatter::itemLines(
            $this->alert['items'],
            20,
            fn (array $item) => MarketStockNotificationFormatter::transitionItemLine($item)
        ) as $line) {
            $message->line($line);
        }

        $message->action('View Market Seeding', $this->alert['dashboard_url']);
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
}
