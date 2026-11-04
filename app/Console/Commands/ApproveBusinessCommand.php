<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\VerificationStatus;
use App\Models\Business;
use Illuminate\Console\Command;

class ApproveBusinessCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'business:approve {id : The ID of the business to approve}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Approve a business for public storefront access and live operations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $id = $this->argument('id');

        $business = Business::find($id);

        if (! $business instanceof Business) {
            $this->error("Business with ID [{$id}] not found.");

            return self::FAILURE;
        }

        $business->verification_status = VerificationStatus::Approved;
        $business->reviewed_at = now();
        $business->save();

        $this->info("Business [{$business->business_name}] (ID: {$business->id}) has been approved successfully.");

        return self::SUCCESS;
    }
}
