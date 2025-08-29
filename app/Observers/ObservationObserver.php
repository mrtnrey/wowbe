<?php

namespace App\Observers;

use App\Jobs\AggregateObservations5min;
use App\Jobs\AggregateObservationsDay;
use App\Models\Observation;

class ObservationObserver
{
    /**
     * Handle the Observation "created" event.
     */
    public function created(Observation $observation): void
    {
        AggregateObservations5min::dispatch($observation->site_id, $observation->dateutc, $observation->site->timezone);
        AggregateObservationsDay::dispatch($observation->site_id, $observation->dateutc, $observation->site->timezone);
    }

    /**
     * Handle the Observation "updated" event.
     */
    public function updated(Observation $observation): void
    {
        //
    }

    /**
     * Handle the Observation "deleted" event.
     */
    public function deleted(Observation $observation): void
    {
        //
    }

    /**
     * Handle the Observation "restored" event.
     */
    public function restored(Observation $observation): void
    {
        //
    }

    /**
     * Handle the Observation "force deleted" event.
     */
    public function forceDeleted(Observation $observation): void
    {
        //
    }
}
