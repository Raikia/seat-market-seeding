<?php

namespace Raikia\SeatMarketSeeding\Notifications\MarketStock\Mail;

use Illuminate\Notifications\Messages\MailMessage;
use Raikia\SeatMarketSeeding\Support\MarketStockNotificationFormatter;
use Seat\Notifications\Notifications\AbstractMailNotification;

class MarketStockRestocked extends AbstractMailNotification
{
    private array $alert;

    public function __construct(array $alert)
    {
        $this->alert = $alert;
    }

    protected function populateMessage(MailMessage $message, $notifiable)
    {
        $message
            ->subject('SeAT Market Seeding: items restocked')
            ->line(sprintf('%s market item%s restocked on %s.', number_format($this->alert['item_count']), $this->alert['item_count'] === 1 ? '' : 's', $this->alert['market_name']))
            ->line(sprintf('Location: %s', $this->alert['location_name']));

        foreach (MarketStockNotificationFormatter::itemLines(
            $this->alert['items'],
            20,
            fn (array $item) => MarketStockNotificationFormatter::restockedItemLine($item)
        ) as $line) {
            $message->line($line);
        }

        $message->action('View Market Seeding', $this->alert['dashboard_url']);
    }
}
