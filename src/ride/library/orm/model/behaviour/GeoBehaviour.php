<?php

namespace ride\library\orm\model\behaviour;

use ride\library\geocode\Geocoder;
use ride\library\orm\entry\GeoEntry;
use ride\library\orm\model\Model;
use ride\library\validation\exception\ValidationException;

/**
 * Behaviour to resolve the coordinates of a location
 */
class GeoBehaviour extends AbstractBehaviour {

    /**
     * Constructs a new behaviour
     * @param \ride\library\geocode\Geocoder $geocoder Instance of a geocoder
     * @param string $geocoderService Name of the service inside the geocoder
     * @return null
     */
    public function __construct(Geocoder $geocoder, $geocoderService) {
        $this->geocoder = $geocoder;
        $this->goecoderService = $geocoderService;
    }

    /**
     * Hook before validation of an entry
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @param \ride\library\validation\exception\ValidationException $exception
     * @return null
     */
    public function preValidate(Model $model, $entry, ValidationException $exception) {
        if (!$entry instanceof GeoEntry) {
            return;
        }

        $address = $entry->getGeoAddress();
        if (!$address) {
            return;
        }

        $geocodeResults = $this->geocoder->geocode($this->geocoderService, $address);
        foreach ($geocodeResults as $geocodeResult) {
            $coordinate = $geocodeResult->getCoordinate();

            $entry->setLatitude($coordinate->getLatitude());
            $entry->setLongitude($coordinate->getLongitude());

            break;
        }
    }

}
