<?php

namespace App\Services\Printing;

use App\Data\Api\TicketPrintData;
use RuntimeException;

class TicketPrinter
{
    public function render(TicketPrintData $ticket, int $copies = 1): string
    {
        $logoDataUri = app(\App\Services\Images\BrandImage::class)->source('ticket');

        $copies = min(10, max(1, $copies));

        return view('printing.ticket', compact('ticket', 'logoDataUri', 'copies'))->render();
    }
}
