<?php

use Icinga\Application\Icinga;
use Icinga\Authentication\Auth;
use Icinga\Data\Filter\FilterMatch;
use Icinga\Module\Map\MappingIniRepository;

$section = $this->menuSection(N_('Maps'), array('icon' => 'globe'));

$mapModule = $section->add(N_($this->translate('Default map')), array(
    'icon' => 'globe',
    'description' => $this->translate('Visualize your hosts and services on a map'),
    'url' => 'map',
    'priority' => 10
));

// stylesheets
$this->provideCssFile('vendor/leaflet.css');
$this->provideCssFile('vendor/MarkerCluster.css');
$this->provideCssFile('vendor/MarkerCluster.Default.css');
$this->provideCssFile('vendor/L.Control.Locate.css');
$this->provideCssFile('vendor/easy-button.css');
$this->provideCssFile('vendor/leaflet.awesome-markers.css');
$this->provideCssFile('vendor/leaflet.modal.css');
$this->provideCssFile('vendor/L.Control.OpenCageData.Search.min.css');

// javascript libraries
$this->provideJsFile('vendor/spin.js');
$this->provideJsFile('vendor/leaflet.js');
$this->provideJsFile('vendor/leaflet.spin.js');
$this->provideJsFile('vendor/leaflet.markercluster.js');
$this->provideJsFile('vendor/L.Control.Locate.js');
$this->provideJsFile('vendor/easy-button.js');
$this->provideJsFile('vendor/leaflet.awesome-markers.js');
$this->provideJsFile('vendor/Leaflet.Modal.js');
$this->provideJsFile('vendor/L.Control.OpenCageSearch.js');

// configuration menu
$this->provideConfigTab('config', array(
    'title' => $this->translate('Configure the map module'),
    'label' => $this->translate('Configuration'),
    'url' => 'config'
));

$this->providePermission('map/mapping', $this->translate('allow access to mapping'));

$section->add(N_('Mapping'))
    ->setUrl('map/mapping')
    ->setPermission('map/mapping')
    ->setPriority(999);



if ($this->app->getModuleManager()->hasEnabled("mapDatatype") && $this->app->getModuleManager()->hasEnabled("director")) {

    $this->provideConfigTab('director', array(
        'title' => $this->translate('Configure the director map datatype'),
        'label' => $this->translate('Director'),
        'url' => 'config/director'
    ));

}

$auth =Auth::getInstance();
if(!Icinga::app()->isCli() && $auth->isAuthenticated()){

    $mappings = (new MappingIniRepository())->select()->addFilter(new FilterMatch('enabled','=',"1"))->fetchAll();
    $isAuthor =  (new MappingIniRepository())->select()->addFilter(new FilterMatch('author','=',$auth->getUser()->getUsername()))->fetchAll();

    if($isAuthor) {
        $section->add(N_('Mapping'))
            ->setUrl('map/mapping')
            ->setPriority(999);
    }

    foreach ($mappings as $mapping){
        $permission = 'map/mapping/'.$mapping->name;
        $this->providePermission($permission, $this->translate('allow access to mapping')." ".$mapping->name);
        if($auth->hasPermission($permission) || $mapping->author === $auth->getUser()->getUsername()){
            $section->add($mapping->name, array(
                'url' => $mapping->url,
                'icon' => 'globe',
                'priority' => $mapping->priority,
                'description' => sprintf($this->translate('Visualize %s on a map'),$mapping->name),
            ));
        }

    }
}
