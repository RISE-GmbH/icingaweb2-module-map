<?php

use Icinga\Application\Modules\Module;
use Icinga\Module\Cube\ProvidedHook\Icingadb\IcingadbSupport;
use OpenCage\Loader\CompatLoader;

$this->provideHook('monitoring/HostActions');
$this->provideHook('monitoring/ServiceActions');
$this->provideHook('cube/Actions', 'CubeLinks');
$this->provideHook('icingadb/IcingadbSupport');
$this->provideHook('icingadb/HostActions');
$this->provideHook('icingadb/ServiceActions');
$this->provideHook('cube/Actions', 'IcingaDbCubeLinks');

require_once __DIR__ . '/library/vendor/OpenCage/Loader/CompatLoader.php';
CompatLoader::delegateLoadingToIcingaWeb($this->app);

if(! (Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend()) ){
    $this->addRoute('map', new Zend_Controller_Router_Route_Static(
        'map',
        [
            'controller'    => 'ido-index',
            'action'        => 'index',
            'module'        => 'map'
        ]
    ));

    $this->addRoute('map/index', new Zend_Controller_Router_Route_Static(
        'map/index',
        [
            'controller'    => 'ido-index',
            'action'        => 'index',
            'module'        => 'map'
        ]
    ));
}