<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Libraries\UnearthSpace\SatellitePredictor;
use App\Libraries\UnearthSpace\LocationCoordinate;
use App\Satellite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Ivory\GoogleMap\Helper\Builder\ApiHelperBuilder;


class SatelliteController extends Controller
{
    protected $validation_messages;

    public function __construct() 
    {
        $this->validation_messages = [
            'required' => 'The :attribute input is required.',
            'numeric'  => 'The :attribute input must be a number.',
            'between'  => 'The :attribute input must be between :min and :max.',
        ];
    }


    public function list() 
    {
        return $response()->success(Satellite::all());
    }


    public function passes(Satellite $satellite, Request $request) 
    {
        $max_days = SatellitePredictor::PASSES_MAX_TIMESPAN_DAYS_WEB;

        $validator = Validator::make($request->input(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'alt' => 'nullable|numeric',
            'days' => "nullable|numeric|between:0,$max_days",
            'extended_details' => 'nullable|boolean'
        ], $this->validation_messages);

        if ($validator->fails()) {
            return response()->error($validator->errors(), 400);
        }

        $location = new LocationCoordinate(
            $request->lat, $request->lng, $request->alt
        );

        $opts = [
            'days' => $request->days,
            'extended_details' => $request->extended_details,
        ];

        $satellite_predictor = new SatellitePredictor($satellite->tle);
        $pass_data = $satellite_predictor->getPasses($location, $opts);

        return response()->success($pass_data);
    }


    public function position(Satellite $satellite, Request $request)
    {
        $max_mins = SatellitePredictor::POSITION_MAX_TIMESPAN_MINUTES_WEB;

        $validator = Validator::make($request->input(), [
            'mins' => "nullable|numeric|between:0,$max_mins"
        ], $this->validation_messages);

        if ($validator->fails()) {
            return response()->error($validator->errors(), 400);
        }

        $satellite_predictor = new SatellitePredictor($satellite->tle);
        
        $opts = [
            'mins' => $request->mins,
        ];

        $position_data = $satellite_predictor->getPosition($opts);

        return response()->success($position_data);
    }
}