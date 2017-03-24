<?php

namespace App\Libraries\UnearthSpace;

use App\Libraries\Predict\Predict;
use App\Libraries\Predict\Predict_QTH;
use App\Libraries\Predict\Predict_TLE;
use App\Libraries\Predict\Predict_Sat;
use App\Libraries\Predict\Predict_Time;
use Ivory\GoogleMap\Base\Coordinate;
// https://github.com/egeloen/ivory-google-map/blob/master/doc/service/timezone/timezone.md
// use Ivory\GoogleMap\Service\Serializer\SerializerBuilder;
use Ivory\GoogleMap\Service\TimeZone\Request\TimeZoneRequest;
use Ivory\GoogleMap\Service\TimeZone\TimeZoneService;
use Http\Adapter\Guzzle6\Client;
use Http\Message\MessageFactory\GuzzleMessageFactory;

/**
 * Interacts with Predict library to provide satellite pass and position data
 */
class SatellitePredictor {

    ### DEFAULT VALUES FOR SATELLITE PASSES
    // whether to include the pass path with each result
    const PASSES_DEFAULT_EXTENDED_DETAILS = false;
    // default number of days to search ahead for passes
    const PASSES_DEFAULT_TIMESPAN_DAYS = 5;
    // max number of days to search ahead for passes (only apply this limit to web
    // requests to prevent misuse / slowdown of API, set no max for internal requests)
    const PASSES_MAX_TIMESPAN_DAYS_WEB = 20;
    // whether to return only passes that will be visible to the observer (true)
    // or all passes (false - e.g. those during daylight hours)
    const PASSES_DEFAULT_VISIBLE_ONLY = true;

    ### DEFAULT VALUES FOR SATELLITE POSITION PREDICTOR
    // default number of minutes to return satellite position for
    const POSITION_DEFAULT_TIMESPAN_MINUTES = 100;
    // max number of minutes to return satellite position for (only apply this limit to
    // web requests to prevent misuse / slowdown of API, set no max for internal requests)
    const POSITION_MAX_TIMESPAN_MINUTES_WEB = 300;
    // number of minutes between each position data entry
    const POSITION_TIME_RESOLUTION_MINUTES = 1;

    ### TIME FACTORS
    const DAY_TO_MINUTES_FACTOR = 24*60; // minutes in a day
    const HOUR_TO_SECONDS_FACTOR = 60*60; // seconds in an hour

    /**
     * @var Predict_Sat The target satellite to predict data for
     */
    protected $sat;

    /**
     * Create a new satellite prediction instance, sets the satellite to predict
     *
     * @param string $tle The 'two line element' set of the target satellite
     *
     * @return void
     */
    public function __construct($tle)
    {
        $tle_lines = explode("\n", $tle);
        $predict_tle = new Predict_TLE($tle_lines[0], $tle_lines[1], $tle_lines[2]);
        $this->sat = new Predict_Sat($predict_tle);
    }

    /**
     * Calculates the times at which $this->sat will be passing over a specific location
     *
     * @param LocationCoordinate $location_coordinate The target location
     * @param array $input_opts An array of options to customise the return data:
     *     'extended_details' - whether to return the pass path
     *     'timespan_days' - how many days in the future to look for passes
     *     'visible_passes_only' - whether to return visible passes or all passes
     * 
     * @return array[] An array containing location and satellite pass data
     */
    public function getPasses(LocationCoordinate $location_coordinate, $input_opts)
    {
        $default_opts = [
            'extended_details' => self::PASSES_DEFAULT_EXTENDED_DETAILS,
            'timespan_days' => self::PASSES_DEFAULT_TIMESPAN_DAYS,
            'visible_passes_only' => self::PASSES_DEFAULT_VISIBLE_ONLY
        ];

        // override default options with $input_opts (array_intersect_key() ensures that
        // only keys present in $default_opts are retained from $input_opts)
        $opts = array_merge($default_opts, array_intersect_key($input_opts, $default_opts));

        // get the current time as Julian Date (daynum)
        $now = Predict_Time::get_current_daynum(); 

        // the observer or groundstation is called QTH in ham radio terms
        $qth = new Predict_QTH;
        $qth->lat = $location_coordinate->getLat();
        $qth->lon = $location_coordinate->getLng();
        $qth->alt = $location_coordinate->getAlt();

        $predict = new Predict;
        $results = $predict->get_passes($this->sat, $qth, $now, $opts['timespan_days']);

        if ($opts['visible_passes_only']) {
            $results = $predict->filterVisiblePasses($results);
        }

        // initialise location return data
        $location = [
            "lat" => $location_coordinate->getLat(),
            "lng" => $location_coordinate->getLng(),
            "alt" => $location_coordinate->getAlt(),
        ];

        // fetch the time zone for the location
        $time_zone_service = new TimeZoneService(new Client(), new GuzzleMessageFactory());
        $time_zone_service->setKey(env('GOOGLE_MAPS_API_KEY'));
        $time_zone_response = $time_zone_service->process(new TimeZoneRequest(
            new Coordinate(39.6034810, -119.6822510),
            new \DateTime()
        ));

        // if available add timezone to location return data
        if ($time_zone_response->getStatus() === "OK") {
            // convert the raw and daylight savings time offsets from seconds to hours
            $raw_offset = $time_zone_response->getRawOffset() / self::HOUR_TO_SECONDS_FACTOR;
            $dst_offset = $time_zone_response->getDstOffset() / self::HOUR_TO_SECONDS_FACTOR;
            
            $location['time_zone_id'] = $time_zone_response->getTimeZoneId();
            $location['time_zone_name'] = $time_zone_response->getTimeZoneName();
            $location['time_zone_offset'] = $raw_offset + $dst_offset;
        }

        $passes = [];

        // build pass return data
        foreach ($results as $result) {
            $pass = [
                "aos_time"  => Predict_Time::daynum2unix($result->visible_aos),
                "aos_az"    => $result->visible_aos_az,
                "aos_el"    => $result->visible_aos_el,
                "tca_time"  => Predict_Time::daynum2unix($result->visible_tca),
                "tca_az"    => $result->visible_max_el_az,
                "tca_el"    => $result->visible_max_el,
                "los_time"  => Predict_Time::daynum2unix($result->visible_los),
                "los_az"    => $result->visible_los_az,
                "los_el"    => $result->visible_los_el,
                "magnitude" => $result->max_apparent_magnitude,
            ];
            
            // if requested, also return the path of the satellite during the pass
            if ($opts['extended_details']) {
                $pass_path = [];

                foreach ($result->details as $pass_path_point) {
                    // skip points where the satellite isn't visible
                    if ($pass_path_point->vis != Predict::SAT_VIS_VISIBLE) {
                        continue;
                    }

                    $pass_path[] = [
                        'time' => Predict_Time::daynum2unix($pass_path_point->time),
                        'lat'  => $pass_path_point->lat,
                        'lng'  => $pass_path_point->lon,
                        'az'   => $pass_path_point->az,
                        'el'   => $pass_path_point->el,
                    ];
                }

                $pass['path'] = $pass_path;
            }

            $passes[] = $pass;
        }

        $passes_data = [
            'location' => $location,
            'passes' => $passes
        ];

        return $passes_data;
    }

    /**
     * Calculates the future position of $this->sat satellite
     *
     * @param $opts array Contains optional parameter: 
     *   - 'timespan_mins' => the 
     *
     * @return array[] 
     */
    public function getPosition($input_opts)
    {
        $default_opts = [
            'timespan_mins' => self::POSITION_DEFAULT_TIMESPAN_MINUTES
        ];

        // override default options with $input_opts (array_intersect_key() ensures that
        // only keys present in $default_opts are retained from $input_opts)
        $opts = array_merge($default_opts, array_intersect_key($opts, $default_opts));

        // get the current time as Julian date (daynum)
        $now = Predict_Time::get_current_daynum();

        // Predict library works with Julian date, so convert time resolution to days
        $dt_min = self::POSITION_TIME_RESOLUTION_MINUTES / self::DAY_TO_MINUTES_FACTOR;

        $position_data = [];

        // build position return data
        for ($i = 0; $i < $opts['timespan_mins']; $i++) {
            $t = $now + ($dt_min * $i);

            $qth = new Predict_QTH();

            $predict = new Predict();
            $predict->predict_calc($this->sat, $qth, $t);

            $position_data[] = [
                'time' => Predict_Time::daynum2unix($t),
                'lat'  => $this->sat->ssplat,
                'lng'  => $this->sat->ssplon,
                'vel'  => $this->sat->velo,
                'alt'  => $this->sat->alt
            ];
        }

        return $position_data;
    }
}