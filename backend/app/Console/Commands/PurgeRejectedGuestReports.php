<?php

namespace App\Console\Commands;

use App\Enums\ReviewStatus;
use App\Models\Report;
use App\Support\ImageStore;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * A guest report an admin rejected keeps its personal details and selfie only for a while (in case of a dispute),
 * then everything is deleted. Scheduled daily in routes/console.php.
 */
class PurgeRejectedGuestReports extends Command
{
    protected $signature = 'actify:purge-rejected-guest-reports {--days= : keep them this many days (default: config actify.guest.rejected_retention_days)}';

    protected $description = 'Delete rejected guest reports (and their pictures) older than the retention period';

    public function handle(ImageStore $images): int
    {
        $days = (int) ($this->option('days') ?? config('actify.guest.rejected_retention_days'));
        $count = 0;

        Report::query()
            ->with('guestReporter')
            ->where('review_status', ReviewStatus::Rejected->value)
            ->where('reviewed_at', '<', now()->subDays($days))
            ->each(function (Report $report) use ($images, &$count) {
                $photoPath = $report->image_path;
                $photoDisk = $report->image_disk;
                $selfiePath = $report->guestReporter?->selfie_path;

                if (! $report->delete()) {
                    throw new RuntimeException('Unable to delete the rejected guest report.');
                }

                $images->delete($photoPath, $photoDisk);
                $images->delete($selfiePath, 'private');
                $count++;
            });

        $this->info("Deleted {$count} rejected guest report(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
