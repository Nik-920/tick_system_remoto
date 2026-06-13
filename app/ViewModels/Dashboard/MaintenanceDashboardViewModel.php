<?php

declare(strict_types=1);

namespace App\ViewModels\Dashboard;

use App\Models\Ticket;
use App\Support\Dashboard\DateRange;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-only data carrier for the maintenance dashboard.
 *
 * Built once by MaintenanceDashboardQuery and consumed by both the web view
 * and the PDF report, guaranteeing the two render identical numbers.
 *
 * Visibility contract: every personal metric here is already scoped to
 * assigned_to = technician. Only `availableToClaimCount` / `availableQueue`
 * are global (open, unassigned, claimable) and are presented as such.
 */
final class MaintenanceDashboardViewModel
{
    /**
     * @param  list<array{key:string,label:string,value:string,hint:string,tone:string}>  $kpis
     * @param  Collection<int,Ticket>  $alerts
     * @param  list<array{ticket:Ticket,score:int,band:string,bandLabel:string}>  $priorityQueue
     * @param  list<array{ticket:Ticket,score:int}>  $availableQueue
     * @param  list<array{location_id:string,name:string,meta:string,total:int,open:int,in_progress:int,resolved:int}>  $topLocations
     * @param  list<array{category_id:string,name:string,total:int}>  $topCategories
     * @param  array<string,int>  $priorityDistribution
     * @param  array<string,int>  $stateDistribution
     * @param  list<array{date:string,label:string,created:int,resolved:int}>  $dailyTrend
     * @param  Collection<int,Ticket>  $recentResolved
     * @param  list<string>  $recommendations
     */
    public function __construct(
        public readonly DateRange $range,
        public readonly string $technicianName,
        public readonly CarbonInterface $generatedAt,
        public readonly array $kpis,
        public readonly int $availableToClaimCount,
        public readonly Collection $alerts,
        public readonly array $priorityQueue,
        public readonly array $availableQueue,
        public readonly array $topLocations,
        public readonly array $topCategories,
        public readonly array $priorityDistribution,
        public readonly array $stateDistribution,
        public readonly array $dailyTrend,
        public readonly Collection $recentResolved,
        public readonly array $recommendations,
    ) {}

    /** Peak value across the daily trend, used to scale the chart bars/sparkline. */
    public function trendPeak(): int
    {
        $peak = 0;

        foreach ($this->dailyTrend as $point) {
            $peak = max($peak, $point['created'], $point['resolved']);
        }

        return $peak;
    }

    /** Largest location bucket, used to scale the horizontal bar widths. */
    public function topLocationPeak(): int
    {
        $peak = 0;

        foreach ($this->topLocations as $location) {
            $peak = max($peak, $location['total']);
        }

        return $peak;
    }

    /** Largest category bucket, used to scale the horizontal bar widths. */
    public function topCategoryPeak(): int
    {
        $peak = 0;

        foreach ($this->topCategories as $category) {
            $peak = max($peak, $category['total']);
        }

        return $peak;
    }
}
