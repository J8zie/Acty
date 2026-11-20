<?php

namespace App\Console\Commands;

use App\Enums\ReviewStatus;
use App\Models\Report;
use App\Support\ImageStore;
use Illuminate\Console\Command;
use RuntimeException;

// Deletes old rejected guest reports and their pictures.
class PurgeRejectedGuestReports extends Command
{
    protected $signature = 'actify:purge-rejected-guest-reports {--days= : keep them this many days (default: config actify.guest.rejected_retention_days)}';

    protected $description = 'Delete rejected guest reports (and their pictures) older than the retention period';

    // Removes each rejected guest report that is older than the keep period.
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
