<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Satellite extends Model
{
    // URL to the set of Satellite TLEs
    const TLE_RESOURCE = 'http://www.celestrak.com/NORAD/elements/visual.txt';
    // the number of lines a TLE set consists of
    const TLE_LINES = 3;

    protected $primaryKey = 'catalog_number';
    protected $fillable   = ['catalog_number', 'name', 'tle_line_1', 'tle_line_2'];
    protected $hidden     = ['created_at'];

    /**
     * Constructs the satellite two line element set in string format
     *
     * @return string The satellite TLE with title, line1 and line2 on separate lines
     */
    public function getTLEAttribute() {
        return implode("\n", [$this->title, $this->tle_line_1, $this->tle_line_2]);
    }

    /**
     * Imports satellite TLE sets into database
     *
     * @return void
     */
    public static function updateTLEs()
    {
        // split the TLE resource into sets of TLE satellite data
        $file_lines = file(self::TLE_RESOURCE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $satellite_tles = array_chunk($file_lines, self::TLE_LINES);

        for ($i = 0; $i < count($satellite_tles); $i++) {
            $tle = $satellite_tles[$i];

            // skip any incomplete TLE set
            if (count($tle) < self::TLE_LINES) {
                continue;
            }

            $title = trim($tle[0]);
            $tle_line_1 = trim($tle[1]);
            $tle_line_2 = trim($tle[2]);

            // extract the Satellite Catalog Number - the primary identifier for satellites
            $line_2_components = explode(" ", $tle_line_2);
            $catalog_number = $line_2_components[1];

            // update existing record if satellite already in DB, otherwise add a new record
            $satellite = Satellite::updateOrCreate(
                ['catalog_number' => $catalog_number],
                ['name' => $title, 'tle_line_1' => $tle_line_1, 'tle_line_2' => $tle_line_2]
            );
        }
    }
}