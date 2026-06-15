<?php

namespace Raikia\SeatMarketSeeding\Notifications\MarketStock\Mail;

use Illuminate\Notifications\Messages\MailMessage;
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
        $title = $empty ? 'Market item is empty' : 'Market item is low';

        $message
            ->subject('SeAT Market Seeding: ' . $title)
            ->line(sprintf('%s on %s.', $this->alert['type_name'], $this->alert['market_name']))
            ->line(sprintf('Location: %s', $this->alert['location_name']))
            ->line(sprintf('Current quantity: %s', number_format($this->alert['current_quantity'])))
            ->line(sprintf('Low warning: %s', number_format($this->alert['warning_quantity'])))
            ->line(sprintf('Target quantity: %s', number_format($this->alert['desired_quantity'])))
            ->line(sprintf('Transition: %s -> %s', $this->alert['previous_status'], $this->alert['current_status']))
            ->action('View Market Seeding', $this->alert['dashboard_url']);
    }
}
