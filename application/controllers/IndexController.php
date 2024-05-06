<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

/* icingaweb2-module-map | GPLv2 */

namespace Icinga\Module\Map\Controllers;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Data\Filter\FilterException;
use Icinga\Module\Icingadb\Common\CommandActions;
use Icinga\Module\Icingadb\Common\Links;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\HoststateSummary;
use Icinga\Module\Icingadb\Redis\VolatileStateResults;
use Icinga\Module\Icingadb\Util\FeatureStatus;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;
use Icinga\Module\Icingadb\Web\Controller;
use Icinga\Module\Icingadb\Web\Control\ViewModeSwitcher;
use ipl\Html\HtmlElement;
use ipl\Orm\Query;
use ipl\Stdlib\Filter;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\SortControl;
use ipl\Web\Url;

class IndexController extends Controller
{
    use CommandActions;

    public function indexAction()
    {

        $compact = $this->view->compact;

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
        );


        if ($this->params->get("default_long") != null) {
            $parameterDefaults['default_long'] = $this->params->get("default_long");
        }
        if ($this->params->get("default_lat") != null) {
            $parameterDefaults['default_lat'] = $this->params->get("default_lat");
        }
        if ($this->params->get("default_zoom") != null) {
            $parameterDefaults['default_zoom'] = $this->params->get("default_zoom");
        }

        $this->params->remove("default_long");
        $this->params->remove("default_lat");
        $this->params->remove("default_zoom");


        $host = $this->getParam("showHost");
        $this->params->remove("showHost");

        $this->addTitleTab(t('Map'));

        $db = $this->getDb();

        $hosts = Host::on($db)->with(['state', 'icon_image', 'state.last_comment']);
        $hosts->getWith()['host.state']->setJoinType('INNER');
        $hosts->setResultSetClass(VolatileStateResults::class);
        $this->handleSearchRequest($hosts, ['address', 'address6']);

        $searchBar = $this->createSearchBar($hosts);

        if ($searchBar->hasBeenSent() && !$searchBar->isValid()) {
            if ($searchBar->hasBeenSubmitted()) {
                $filter = $this->getFilter();
            } else {
                $this->addControl($searchBar);
                $this->sendMultipartUpdate();
                return;
            }
        } else {
            $filter = $searchBar->getFilter();
        }

        $this->filter($hosts, $filter);

        $this->addControl($searchBar);

        $config = $this->Config();
        $map = null;
        $mapConfig = null;

        $id = uniqid();
        $expand = $this->params->get("expand");

        $isUsingIcingadb = true;


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
                //$this->view->$parameter = $this->params->get($parameter);
                $parameterDefaults[$parameter] = $this->params->has($parameter);

            } elseif (isset($map) && $mapConfig->getSection($map)->offsetExists($parameter)) {
                //$this->view->$parameter = $mapConfig->get($map, $parameter);
                $parameterDefaults[$parameter] = $mapConfig->get($map, $parameter);

            } else {
                //$this->view->$parameter = $config->get("map", $parameter, $default);
                $parameterDefaults[$parameter] = $config->get("map", $parameter, $default);
            }
        }

        if (!$parameterDefaults['disable_cluster_at_zoom']) {
            $parameterDefaults['disable_cluster_at_zoom'] = $parameterDefaults['max_zoom'] - 1;
        }


        $dashletHeight = $config->get('map', 'dashlet_height', '300');

        $url_parameters = "";


        $this->addContent(HtmlElement::create("style", [],
            ".leaflet-default-icon-path {
                        /* used only in path-guessing heuristic, see L.Icon.Default */
                        background-image: url(img/map/marker-icon.png);
                    }"
        ));

        if ($compact) {
            $this->addContent(HtmlElement::create("div", ['class' => "map compact", 'style' => 'height:' . $dashletHeight . "px", 'id' => "map-" . $id], ""));
        } else {
            $this->addContent(HtmlElement::create("div", ['class' => "map", 'id' => "map-" . $id], ""));
        }

        $script = sprintf("var map_default_zoom = %s;\n", !empty($parameterDefaults['default_zoom']) ? intval($parameterDefaults['default_zoom']) : "null");
        $script .= sprintf("var map_default_long = %s;\n", !empty($parameterDefaults['default_long']) ? preg_replace("/[^0-9\.\,\-]/", "", $parameterDefaults['default_long']) : "null");
        $script .= sprintf("var map_default_lat = %s;\n", !empty($parameterDefaults['default_lat']) ? preg_replace("/[^0-9\.\,\-]/", "", $parameterDefaults['default_lat']) : "null");
        $script .= sprintf("var map_max_zoom = %s;\n", intval($parameterDefaults['max_zoom']));
        $script .= sprintf("var map_max_native_zoom = %s;\n", intval($parameterDefaults['max_native_zoom']));
        $script .= sprintf("var map_min_zoom = %s;\n", intval($parameterDefaults['min_zoom']));
        $script .= sprintf("var disable_cluster_at_zoom = %s;\n", intval($parameterDefaults['disable_cluster_at_zoom']));
        $script .= sprintf("var tile_url = '%s';\n", preg_replace("/[\'\;]/", "", $parameterDefaults['tile_url']));
        $script .= sprintf("var cluster_problem_count = %s;\n", intval($parameterDefaults['cluster_problem_count']));
        $script .= sprintf("var popup_mouseover = %s;\n", intval($parameterDefaults['popup_mouseover']));
        $script .= sprintf("var map_show_host = '%s';\n", preg_replace("/[\'\;]/", "", $host ?? ''));

        $script .= sprintf("var url_parameters = '%s';\n", $url_parameters);
        $script .= sprintf("var id = '%s';\n", $id);
        $script .= sprintf("var dashlet = %s;\n", $compact ? 'true' : 'false');
        $script .= sprintf("var expand = %s;\n", $expand ? 'true' : 'false');
        $script .= sprintf("var isUsingIcingadb = %s;\n", $isUsingIcingadb ? 'true' : 'false');
        $script .= "var service_status = {};\n";

        $script .= sprintf("service_status[0] = ['%s','%s'];\n", $this->translate('OK', 'icinga.state'), 'OK');
        $script .= sprintf("service_status[1] = ['%s','%s'];\n", $this->translate('WARNING', 'icinga.state'), 'WARNING');
        $script .= sprintf("service_status[2] = ['%s','%s'];\n", $this->translate('CRITICAL', 'icinga.state'), 'CRITICAL');
        $script .= sprintf("service_status[3] = ['%s','%s'];\n", $this->translate('UNKNOWN', 'icinga.state'), 'UNKNOWN');
        $script .= sprintf("service_status[99] = ['%s','%s'];\n", $this->translate('PENDING', 'icinga.state'), 'PENDING');
        $toTranslate = array(
            "btn-zoom-in" => "Zoom in",
            "btn-save" => "Add as custom map",
            "btn-zoom-out" => "Zoom out",
            "btn-dashboard" => "Add to dashboard",
            "btn-fullscreen" => "Fullscreen",
            "btn-default" => "Show default view",
            "btn-locate" => "Show current location",
            "host-down" => "Host is down"
        );

        $script .= sprintf("var translation = {");
        foreach ($toTranslate as $key => $value) {
            $script .= sprintf("'%s': '%s',", $key, $this->translate($value));
        }
        $script .= sprintf("};\n");

        $this->addContent(HtmlElement::create("script", [], $script));

        if (!$searchBar->hasBeenSubmitted() && $searchBar->hasBeenSent()) {
            $this->sendMultipartUpdate();
        }

    }


    public function completeAction()
    {
        $suggestions = new ObjectSuggestions();
        $suggestions->setModel(Host::class);
        $suggestions->forRequest(ServerRequest::fromGlobals());
        $this->getDocument()->add($suggestions);
    }

    public function searchEditorAction()
    {
        $editor = $this->createSearchEditor(Host::on($this->getDb()), [
            LimitControl::DEFAULT_LIMIT_PARAM,
            SortControl::DEFAULT_SORT_PARAM,
            ViewModeSwitcher::DEFAULT_VIEW_MODE_PARAM,
            'columns'
        ]);

        $this->getDocument()->add($editor);
        $this->setTitle(t('Adjust Filter'));
    }

    protected function fetchCommandTargets(): Query
    {
        $db = $this->getDb();

        $hosts = Host::on($db)->with('state');
        $hosts->setResultSetClass(VolatileStateResults::class);

        switch ($this->getRequest()->getActionName()) {
            case 'acknowledge':
                $hosts->filter(Filter::equal('state.is_problem', 'y'))
                    ->filter(Filter::equal('state.is_acknowledged', 'n'));

                break;
        }

        $this->filter($hosts);

        return $hosts;
    }

    protected function getCommandTargetsUrl(): Url
    {
        return Links::hostsDetails()->setFilter($this->getFilter());
    }

    protected function getFeatureStatus()
    {
        $summary = HoststateSummary::on($this->getDb());
        $this->filter($summary);

        return new FeatureStatus('host', $summary->first());
    }
}
