<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Map\Util;


use InvalidArgumentException;
use ipl\Web\Filter\QueryString;
use ipl\Web\Url;

class CustomUrlMigrator extends UrlMigrator
{
    const SUPPORTED_PATHS = [
        'map' => ['hosts', 'map'],
    ];

    public static function isSupportedUrl(Url $url): bool
    {
        $supportedPaths = self::SUPPORTED_PATHS;
        return isset($supportedPaths[ltrim($url->getPath(), '/')]);
    }

    public static function transformUrl(Url $url): Url
    {
        if (!self::isSupportedUrl($url)) {
            throw new InvalidArgumentException(sprintf('Url path "%s" is not supported', $url->getPath()));
        }

        list($queryTransformer, $dbRoute) = self::SUPPORTED_PATHS[ltrim($url->getPath(), '/')];

        $url = clone $url;
        $url->setPath($dbRoute);

        $params = $url->getParams();
        $default_lat = $params->get("default_lat");
        $default_long = $params->get("default_long");
        $default_zoom = $params->get("default_zoom");

        $params->remove("default_lat");
        $params->remove("default_long");
        $params->remove("default_zoom");
        $url->setParams($params);

        if (!$url->getParams()->isEmpty()) {
            $filter = QueryString::parse((string)$url->getParams());
            $filter = self::transformFilter($filter, $queryTransformer);
            if ($filter) {
                $url->setParams([])->setFilter($filter);
            }
        }
        if (isset($default_zoom)) {
            $url->setParam("default_zoom", $default_zoom);
        }
        if (isset($default_lat)) {
            $url->setParam("default_lat", $default_lat);
        }
        if (isset($default_long)) {
            $url->setParam("default_long", $default_long);
        }

        return $url;
    }

}