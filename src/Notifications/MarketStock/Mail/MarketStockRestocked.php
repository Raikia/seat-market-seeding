<?php

namespace Raikia\SeatMarketSeeding\Notifications\MarketStock\Mail;

use Illuminate\Notifications\Messages\MailMessage;
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

        foreach (array_slice($this->alert['items'], 0, 20) as $item) {
            $message->line(sprintf(
                '%s: stock %s / target %s (was %s)',
                $item['type_name'],
                number_format($item['current_quantity']),
                number_format($item['desired_quantity']),
                $item['previous_status']
            ));
        }

        if ($this->alert['item_count'] > 20) {
            $message->line(sprintf('...and %s more.', number_format($this->alert['item_count'] - 20)));
        }

        $message->action('View Market Seeding', $this->alert['dashboard_url']);
    }
}
