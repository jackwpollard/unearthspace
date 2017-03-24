<?php

namespace App\Libraries\UnearthSpace;

/**
 * Simple class to encapsulate latitude, longitude, and altitude components of a location
 */
class LocationCoordinate {

	const DEFAULT_ALT = 0;

	private $lat;
	private $lng;
	private $alt;

	public function __construct($lat, $lng, $alt)
	{
		$this->lat = $lat;
		$this->lng = $lng;
		$this->alt = $alt ?: self::DEFAULT_ALT;
	}

	public function getLat()
	{
		return $this->lat;
	}

	public function getLng()
	{
		return $this->lng;
	}

	public function getAlt()
	{
		return $this->alt;
	}
}