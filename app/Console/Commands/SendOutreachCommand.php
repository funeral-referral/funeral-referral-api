<?php

namespace App\Console\Commands;

use App\Outreach\CampaignSender;
use Illuminate\Console\Command;

class SendOutreachCommand extends Command
{
    protected $signature = 'outreach:send';

    protected $description = 'Send approved outreach emails that are due';

    public function handle(CampaignSender $sender): int
    {
        $stats = $sender->sendDue();

        $this->info("Sent {$stats['sent']}, skipped {$stats['skipped']}, failed {$stats['failed']}.");

        return self::SUCCESS;
    }
}
