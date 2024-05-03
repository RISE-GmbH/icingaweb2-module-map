<?php

namespace Icinga\Module\Map;

use Icinga\Application\Logger;
use Icinga\Web\Url;
use OpenCage\Geocoder\AbstractGeocoder;

class OpenStreetMapGeocoder extends AbstractGeocoder
{
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function geocode($query, $optParams)
    {
        $result = [];
        $params = ['q' => $query, 'format' => 'json'];
        if (is_array($optParams) && !empty($optParams)) {
            foreach ($optParams as $param => $paramValue) {
                $params[$param] = $paramValue;
            }
        }

        $search_url = Url::fromPath('search', $params)
            ->setIsExternal(true)
            ->setHost('nominatim.openstreetmap.org')->setBasePath('')
            ->setScheme('https')->getAbsoluteUrl();

        $json = $this->getJSONByCurl($search_url);

        if($json != null){
            $decoded = json_decode($json, true);
            $result['total_results'] = 0;
            $result['results'] = [];
            foreach ($decoded as $el) {
                $result['total_results'] += 1;
                $result['results'][] = ['formatted' => $el['display_name'], 'geometry' => ['lat' => $el['lat'], 'lng' => $el['lon']]];
            }
        }else{
            Logger::error("OpenStreetMap query failed!");
        }

        return $result;
    }

    protected function getJSONByCurl($url)
    {
        $ch = curl_init();
        $options = [
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_USERAGENT => 'IcingaWeb2-Map-Module'
        ];
        curl_setopt_array($ch, $options);

        $ret = curl_exec($ch);
        return $ret;
    }
}