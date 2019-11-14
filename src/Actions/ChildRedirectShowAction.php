<?php

namespace TCG\Voyager\Actions;

class ChildRedirectShowAction extends AbstractAction
{
    public function getTitle()
    {
        return __('voyager::generic.child_redirect');
    }

    public function getIcon()
    {
        return $this->dataType->icon_class;
    }

    public function getPolicy()
    {
        return 'read';
    }

    public function getRediractable()
    {
        return 'redirect';
    }

    public function getAttributes()
    {
        return [
            'class' => 'btn btn-sm btn-warning pull-right redirect',
            'data-id' => $this->data->{$this->data->getKeyName()},
            'data-type' =>'read',
            'id'      => 'redirect-'.$this->data->{$this->data->getKeyName()},
        ];
    }

    public function getDefaultRoute()
    {
        return route('voyager.'.$this->dataType->slug.'.redirect',['id'=>$this->data->id,'parent'=>$this->dataType->redirectParent,'type'=>$this->getPolicy()]);
    }
}
