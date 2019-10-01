<?php

namespace TCG\Voyager\Actions;

class ChildRedirectEditAction extends AbstractAction
{
    public function getTitle()
    {
        return __('voyager::generic.child_redirect');
    }

    public function getIcon()
    {
        return 'voyager-edit';
    }

    public function getPolicy()
    {
        return 'edit';
    }

    public function getRediractable()
    {
        return 'redirect';
    }

    public function getAttributes()
    {
        return [
            'class' => 'btn btn-sm btn-primary pull-right redirect',
            'data-id' => $this->data->{$this->data->getKeyName()},
            'data-type' =>'edit',
            'id'      => 'redirect-'.$this->data->{$this->data->getKeyName()},
        ];
    }

    public function getDefaultRoute()
    {
        return 'javascript:;';
    }
}
