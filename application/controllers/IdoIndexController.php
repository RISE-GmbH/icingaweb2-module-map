<?php

namespace Icinga\Module\Map\Controllers;

use Icinga\Data\Filter\FilterException;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Module\Monitoring\Controller;
use Icinga\Module\Monitoring\DataView\DataView;

use ipl\Web\Compat\SearchControls;

class IdoIndexController extends Controller
{
    use SearchControls {
        SearchControls::createSearchBar as private webCreateSearchBar;
    }

    protected $coordinatePattern = '/^(\-?\d+(\.\d+)?),\s*(\-?\d+(\.\d+)?)$/';
    protected $filter;

    /**
     * Apply filters on a DataView
     *
     * @param DataView  $dataView       The DataView to apply filters on
     *
     * @return DataView $dataView
     */
    protected function filterQuery(DataView $dataView)
    {
        $this->setupFilterControl($dataView, null, null, array(
            'format', // handleFormatRequest()
            'stateType', // hostsAction() and servicesAction()
            'addColumns', // addColumns()
            'problems', // servicegridAction()
            'flipped', // servicegridAction()
            'limit', // setupPaginationControl()
            'sort', // setupSortControl()
            'dir', // setupSortControl()
            'backend', // Framework
            'showCompact', // Framework
            'showHost', // Framework
            'objectType', // Framework
            '_dev', // Framework
            "default_zoom",
            "default_long",
            "default_lat",
            "min_zoom",
            "max_zoom",
            "max_native_zoom",
            "disable_cluster_at_zoom", // should be by default: max_zoom - 1
            "cluster_problem_count",
            "tile_url",
        ));

        if ($this->params->get('format') !== 'sql' || $this->hasPermission('config/authentication/roles/show')) {
            $this->applyRestriction('monitoring/filter/objects', $dataView);
        }

        return $dataView;
    }

    public function indexAction()
    {
        $this->_helper->viewRenderer->setRender('index/index', null, true);

        $config = $this->Config();
        $map = null;
        $mapConfig = null;
        $this->backend = MonitoringBackend::instance($this->_getParam('backend'));

        // try to load stored map
        if ($this->params->has("load")) {
            $map = $this->params->get("load");

            if (!preg_match("/^[\w]+$/", $map)) {
                throw new FilterException("Invalid character in map name. Allow characters: a-zA-Z0-9_");
            }

            $mapConfig = $this->Config("maps");
            if (!$mapConfig->hasSection($map)) {
                throw new FilterException("Could not find stored map with name = " . $map);
            }
        }

        if (strtolower($this->params->shift('stateType', 'soft')) === 'hard') {
            $stateColumn = 'host_hard_state';
            $stateChangeColumn = 'host_last_hard_state_change';
        } else {
            $stateColumn = 'host_state';
            $stateChangeColumn = 'host_last_state_change';
        }


        $this->view->id = uniqid();
        $this->view->host = $this->params->get("showHost");
        $this->view->expand = $this->params->get("expand");
        $this->view->fullscreen = ($this->params->get("showFullscreen") == 1);
        $this->view->isUsingIcingadb = false;

        $hosts = $this->backend->select()->from('hoststatus', array_merge(array(
            'host_icon_image',
            'host_icon_image_alt',
            'host_name',
            'host_display_name',
            'host_state' => $stateColumn,
            'host_acknowledged',
            'host_output',
            'host_attempt',
            'host_in_downtime',
            'host_is_flapping',
            'host_state_type',
            'host_handled',
            'host_last_state_change' => $stateChangeColumn,
            'host_notifications_enabled',
            'host_active_checks_enabled',
            'host_passive_checks_enabled',
            'host_check_command',
            'host_next_update'
        )));

        $this->filterQuery($hosts);


        $parameterDefaults = array(
            "default_zoom" => "4",
            "default_long" => '13.377485',
            "default_lat" => '52.515855',
            "min_zoom" => "2",
            "max_zoom" => "19",
            "max_native_zoom" => "19",
            "disable_cluster_at_zoom" => null, // should be by default: max_zoom - 1
            "cluster_problem_count" => 0,
            "popup_mouseover" => 0,
            "tile_url" => "//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
            "opencage_apikey" => "",
        );

        /*
         * 1. url
         * 2. stored map
         * 3. user config
         * 4. config
         */
        $userPreferences = $this->Auth()->getUser()->getPreferences();
        if ($userPreferences->has("map")) {
            $config->getSection("map")->merge($userPreferences->get("map"));
        }

        foreach ($parameterDefaults as $parameter => $default) {
            if ($this->params->has($parameter)) {
                $this->view->$parameter = $this->params->get($parameter);
            } elseif (isset($map) && $mapConfig->getSection($map)->offsetExists($parameter)) {
                $this->view->$parameter = $mapConfig->get($map, $parameter);
            } else {
                $this->view->$parameter = $config->get("map", $parameter, $default);
            }
        }

        if (!$parameterDefaults['disable_cluster_at_zoom']) {
            $this->view->disable_cluster_at_zoom = $parameterDefaults['max_zoom'] - 1;
        }

        #params are already there keep empty and remove in future
        $this->view->url_parameters = "";

        $this->view->dashletHeight = $config->get('map', 'dashlet_height', '300');
    }


}
