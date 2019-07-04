<?php

namespace TCG\Voyager\Actions;

class ChildRedirectAction extends AbstractAction
{
    public function getTitle()
    {
        return __('voyager::generic.child_redirect');
    }

    public function getIcon()
    {
        return 'voyager-tree';
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
            'class' => 'btn btn-sm btn-primary pull-right redirect',
            'style' => 'margin: 0px 5px 0px 1px; padding: 3px 9px;',
            'data-id' => $this->data->{$this->data->getKeyName()},
            'id'      => 'redirect-'.$this->data->{$this->data->getKeyName()},
        ];
    }

    public function getDefaultRoute()
    {
        return 'javascript:;';
    }
}
