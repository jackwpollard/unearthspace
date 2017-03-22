<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Satellite;
use DB;

class UpdateSatelliteTLEs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'satellites:update-tles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the TLE sets for all satellites';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        Satellite::updateTLEs();

        $last_updated = DB::table('satellites')
                          ->orderBy('updated_at', 'desc')
                          ->value('updated_at');

        $this->info("Satellite TLEs last modified at $last_updated");
    }
}
