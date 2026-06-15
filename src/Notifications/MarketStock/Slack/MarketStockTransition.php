<?php

namespace Raikia\SeatMarketSeeding\Notifications\MarketStock\Slack;

use Illuminate\Notifications\Messages\SlackMessage;
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
        $title = $empty ? 'Market item is empty' : 'Market item is low';

        $message
            ->content($title)
            ->from('SeAT Market Seeding')
            ->attachment(function ($attachment) use ($title, $empty) {
                $attachment
                    ->title($title, $this->alert['dashboard_url'])
                    ->fields([
                        'Market' => sprintf('%s (%s)', $this->alert['market_name'], $this->alert['location_name']),
                        'Item' => $this->alert['type_name'],
                        'Current' => number_format($this->alert['current_quantity']),
                        'Low Warning' => number_format($this->alert['warning_quantity']),
                        'Target' => number_format($this->alert['desired_quantity']),
                        'Transition' => $this->alert['previous_status'] . ' -> ' . $this->alert['current_status'],
                    ])
                    ->color($empty ? 'danger' : 'warning');
            });

        $empty ? $message->error() : $message->warning();
    }
}
