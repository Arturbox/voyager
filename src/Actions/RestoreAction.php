<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 3/21/2019
 * Time: 7:22 PM
 */

namespace TCG\Voyager\Actions;

class RestoreAction extends AbstractAction
{
    public function getTitle()
    {
        return __('voyager::generic.restore');
    }

    public function getIcon()
    {
        return 'voyager-restore';
    }

    public function getPolicy()
    {
        return 'restore';
    }

    public function getAttributes()
    {
        return [
            'class'   => 'btn btn-sm btn-danger pull-right restore',
            'data-id' => $this->data->{$this->data->getKeyName()},
            'id'      => 'restore-'.$this->data->{$this->data->getKeyName()},
        ];
    }

    public function getDefaultRoute()
    {
        return 'javascript:;';
    }
}
