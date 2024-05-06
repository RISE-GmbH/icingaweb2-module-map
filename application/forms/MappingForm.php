<?php
/* Originally from Icinga Web 2 Elasticsearch Module (c) 2017 Icinga Development Team | GPLv2+ */
/* generated by icingaweb2-module-scaffoldbuilder | GPLv2+ */

namespace Icinga\Module\Map\Forms;

use Icinga\Application\Modules\Module;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\Http\HttpMethodNotAllowedException;
use Icinga\Forms\RepositoryForm;
use Icinga\Module\Map\MappingIniRepository;
use Icinga\Module\Map\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Map\Util\CustomUrlMigrator;

/**
 * Create, update and delete a Config
 */
class MappingForm extends RepositoryForm
{

    public function init()
    {
        $this->repository = new MappingIniRepository();
        $this->redirectUrl = 'map/mapping';

    }
    /**
     * Prepare the form for the requested mode
     * Clear out protectedFields
     */
    public function fetchEntry()
    {
        $entry = parent::fetchEntry();

        if($this->getRequest()->getParam("url") != null){
            $entry->url=$this->getRequest()->getParam("url");
        }
        return $entry;
    }


    /**
     * Set the identifier
     *
     * @param   string  $identifier
     *
     * @return  $this
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * Set the mode of the form
     *
     * @param   int $mode
     *
     * @return  $this
     */
    public function setMode($mode)
    {
        $this->mode = $mode;

        return $this;
    }

    protected function onUpdateSuccess()
    {
        if ($this->getElement('btn_remove')->isChecked()) {
            $this->setRedirectUrl("map/mapping/delete?id={$this->getIdentifier()}");
            $success = true;
        }elseif ( $this->getElement('btn_migrate') != null && $this->getElement('btn_migrate')->isChecked()) {
            $entry = (array) $this->repository->select()->where('name', $this->getIdentifier())->fetchRow();
            $entry['url'] = CustomUrlMigrator::transformUrl(\ipl\Web\Url::fromPath($entry['url']))->getRelativeUrl();
            $this->setRedirectUrl(\ipl\Web\Url::fromPath("map/mapping/update",["id"=>$this->getIdentifier(),"url"=>$entry['url']]));
            $success=true;

        } else {
            $success = parent::onUpdateSuccess();
        }

        return $success;
    }

    protected function createBaseElements(array $formData)
    {
        $this->addElement(
            'text',
            'name',
            array(
                'description'   => $this->translate('The name of the the custom map'),
                'label'         => $this->translate('Name'),
                'required'      => true,
            )

        );
        $this->addElement(
            'text',
            'author',
            array(
                'description'   => $this->translate('The author of the the custom map, this user will always have access to this map'),
                'label'         => $this->translate('Author'),
                'required'      => true,
                'value'=>$this->Auth()->getUser()->getUsername()
            )

        );
        $this->addElement(
            'number',
            'priority',
            array(
                'description'   => $this->translate('The priority of the  custom map in the menu'),
                'label'         => $this->translate('Priority'),
                'required'      => false,
                'value'=>10,
            )

        );

        $this->addElement(
            'text',
            'url',
            array(
                'description'   => $this->translate('The url to the custom map'),
                'label'         => $this->translate('Url'),
                'required'      => false,
                'value'=>$this->getRequest()->getParam("url"),
            )

        );

        $this->addElement(
            'checkbox',
            'enabled',
            array(
                'description'       => $this->translate('Enable or disable this entry'),
                'label'             => $this->translate('Enabled'),
                'value'=>1
            )
        );

    }

    protected function createInsertElements(array $formData)
    {
        $this->createBaseElements($formData);

        $this->setTitle($this->translate('Create a New Custom Map'));

        $this->setSubmitLabel($this->translate('Save'));
    }

    protected function createUpdateElements(array $formData)
    {
        $this->createBaseElements($formData);

        $this->setTitle(sprintf($this->translate('Update Custom Map %s'), $this->getIdentifier()));

        $this->addElement(
            'submit',
            'btn_submit',
            [
                'decorators'            => ['ViewHelper'],
                'ignore'                => true,
                'label'                 => $this->translate('Save')
            ]
        );

        $this->addElement(
            'submit',
            'btn_remove',
            [
                'decorators'            => ['ViewHelper'],
                'ignore'                => true,
                'label'                 => $this->translate('Remove')
            ]
        );


        if($formData['url'] != CustomUrlMigrator::transformUrl(\ipl\Web\Url::fromPath($formData['url']))->getRelativeUrl() && Module::exists("icingadb") && IcingadbSupport::useIcingaDbAsBackend()){
            $this->addElement(
                'submit',
                'btn_migrate',
                [
                    'decorators'            => ['ViewHelper'],
                    'ignore'                => true,
                    'label'                 => $this->translate('Migrate to IcingaDB')
                ]
            );
        }
        $this->addDisplayGroup(
            ['btn_submit', 'btn_remove', 'btn_migrate'],
            'form-controls',
            [
                'decorators' => [
                    'FormElements',
                    ['HtmlTag', ['tag' => 'div', 'class' => 'control-group form-controls']]
                ]
            ]
        );

    }

    public function onSuccess()
    {
        if(! $this->hasPermission('map/mapping')){
            if($this->getValue('author') !== $this->Auth()->getUser()->getUsername()){
                throw new HttpMethodNotAllowedException(t('You don`t have the permission to set maps for other users'));
            }
        }
        return parent::onSuccess();
    }

    protected function createDeleteElements(array $formData)
    {
        $this->setTitle(sprintf($this->translate('Remove Custom Map %s'), $this->getIdentifier()));

        $this->setSubmitLabel($this->translate('Yes'));
    }

    protected function createFilter()
    {
        return Filter::where('name', $this->getIdentifier());
    }

    protected function getInsertMessage($success)
    {
        return $success
            ? $this->translate('Custom Map created')
            : $this->translate('Failed to create Custom Map');
    }

    protected function getUpdateMessage($success)
    {
        return $success
            ? $this->translate('Custom Map updated')
            : $this->translate('Failed to update Custom Map');
    }

    protected function getDeleteMessage($success)
    {
        return $success
            ? $this->translate('Custom Map removed')
            : $this->translate('Failed to remove Custom Map');
    }
}
