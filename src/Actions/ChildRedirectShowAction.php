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
        return 'voyager-eye';
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
        return 'javascript:;';
    }
}
